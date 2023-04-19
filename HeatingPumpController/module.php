<?php

/**
 * @project       HeatingPumpController
 * @file          module.php
 * @author        Alfred Schorn
 * @copyright     2023 Alfred Schorn
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 */

/** @noinspection PhpUnhandledExceptionInspection */
/** @noinspection PhpUnused */

declare(strict_types=1);

include_once __DIR__ . '../../libs/RBH_ModuleFunctions.php';

class HeatingPumpController extends IPSModule
{
    use RBH_ModuleFunctions;


    //Constants
    private const LIBRARY_GUID = '{B56AF957-4693-C8FE-B78D-C4091976F4C9}';
    private const MODULE_NAME = 'HeatingPumpController';
    private const MODULE_PREFIX = 'HPCTRL';
    private const MODULE_VERSION = '1.0, 14.02.2023';
    private const MINIMUM_DELAY_MILLISECONDS = 100;

    private const ProfileList =                     'HeatPumpRequest,OpMode,BoostMode,HeatPumpReleasePower';
    private const RegisterVariablesUpdateList =     '';
    private const RegisterReferenciesUpdateList =   'ExpertModeID,IdPvPower,IdControlAlive';
    private const ReferenciesList =                 'ExpertModeID,IdPvPower,IdOpModeSend,IdHeatPumpRelease,IdHeatPumpNightLock,IdControlAlive,IdHeatPumpReleaseStatus';
    private const PropertiesList =                  'ExpertModeID,IdPvPower,IdOpModeSend,IdHeatPumpRelease,IdHeatPumpNightLock,IdControlAlive,IdHeatPumpReleaseStatus';
    private const ExpertLockList =                  '';
    private const ExpertHideList =                  'HeatPumpReleasePower';
   
    private const Aus = 0;
    private const Manuell = 1;
    private const Automatik = 2;


    private const HpOff = 0;
    private const HpPvLimit = 1;
    private const HpNightLock = 3;
    private const HpBoostMode = 4;
    private const HpRequested = 5;

    private const WsUndef = 0;
    private const WsReleased = 1;
    private const WsLocked = 2;
    

    private const Transparent = 0xffffff00;
    private const Red = 0xFF0000;
    private const Yellow = 0xFFFF00;
    private const Green=0x00FF00;
    private const Blue=0x0000FF;
    private const DarkGreen = 0x24F065;
    private const DarkBlue  = 0x0D0FE4;
  
    public function Create()
    {
        //Never delete this line!
        parent::Create();

        ########## Properties
        $this->DeleteProfileList (self::ProfileList);
     
        $this->RegisterPropertyInteger('WeekTimerGroups',0);
        $this->RegisterPropertyInteger('BoostTime',2);
        $this->RegisterPropertyFloat('PvLimitHysteresis',300.0);
       
        ########## Variables

       //Variablen --------------------------------------------------------------------------------------------------------
       
       //OpMode
        $variable = 'OpMode';
        $profileName =  $this->CreateProfileName($variable);
        if (!IPS_VariableProfileExists($profileName)) {
            IPS_CreateVariableProfile($profileName, 1);
            IPS_SetVariableProfileValues($profileName, 0, 2, 0);
            IPS_SetVariableProfileIcon($profileName, "Shutter");
            IPS_SetVariableProfileAssociation($profileName, 0, "Aus", "", self::Red);
            IPS_SetVariableProfileAssociation($profileName, 1, "Manuell", "", self::Yellow);
            IPS_SetVariableProfileAssociation($profileName, 2, "Automatik", "", self::Green);
        }
        $this->RegisterVariableInteger($variable, $this->Translate('Operation Mode'),$profileName, 10);
        $this->EnableAction($variable);

       
        //BoostMode
        $variable = 'BoostMode';
        $profileName = $this->CreateProfileName($variable);
        if (!IPS_VariableProfileExists($profileName)) {
            IPS_CreateVariableProfile($profileName, 0);
            IPS_SetVariableProfileIcon($profileName, "Ok");
            IPS_SetVariableProfileAssociation($profileName, false, "Nein", "", self::Transparent);
            IPS_SetVariableProfileAssociation($profileName, true, "Ja", "", self::Yellow);
        }
        $this->RegisterVariableBoolean($variable,"Zuheizen", $profileName, 20);
        $this->EnableAction($variable);

         //HeatPumpRequest
        $variable = 'HeatPumpRequest';
        $profileName = $this->CreateProfileName($variable);
        if (!IPS_VariableProfileExists($profileName)) {
           IPS_CreateVariableProfile($profileName, 1);
           IPS_SetVariableProfileIcon($profileName, "Shutter");
           IPS_SetVariableProfileAssociation($profileName, self::HpOff, "Keine Anforderung", "", self::Yellow);
           IPS_SetVariableProfileAssociation($profileName, self::HpPvLimit,"Warte auf minimale PV-Leistung", "", self::Yellow);
           IPS_SetVariableProfileAssociation($profileName, self::HpNightLock,"Nachtsperre aktiv", "", self::Blue);
           IPS_SetVariableProfileAssociation($profileName, self::HpRequested,"Angefordert", "", self::Green);
        }
        $this->RegisterVariableInteger($variable, $this->Translate('Heat Pump Request'), $profileName, 30);
        //$this->EnableAction($variable);

        //HeatPumpRelease
        $variable = 'HeatPumpReleasePower';
        $profileName = $this->CreateProfileName($variable);
        if (!IPS_VariableProfileExists($profileName)) {
            IPS_CreateVariableProfile($profileName, 2);
            IPS_SetVariableProfileIcon($profileName, "Electricity");
            IPS_SetVariableProfileValues($profileName, 0, 500, 0);
            IPS_SetVariableProfileDigits($profileName, 1);
            IPS_SetVariableProfileText($profileName,"","W");
        }
        $this->RegisterVariableFloat($variable, $this->Translate('Minimum PV Power'), $profileName, 40);
        $this->EnableAction($variable);

        //Alle in der "form.json" definierten Variablenreferenzen registrieren
        $this->RegisterVariableIds(self::PropertiesList);

        //Id Speicher für Weektimer vorbereiten
        $this->RegisterAttributeInteger('WeekTimerPv', 0);
        $this->RegisterAttributeInteger('PvPowerLink', 0);
        $this->RegisterAttributeInteger('WeekTimerStatus', 0);
       
       
        ########## Timer
       //------------------------------------------------------------------------------------------------------------------
       //Timer ------------------------------------------------------------------------------------------------------------
       $this->RegisterTimer('HPCTRL_BoostTimer', 0, 'HPCTRL_UpdateTimer($_IPS[\'TARGET\']);');
       //------------------------------------------------------------------------------------------------------------------
    }

    public function ApplyChanges()
    {
        //Wait until IP-Symcon is started
        $this->RegisterMessage(0, IPS_KERNELSTARTED);

        //Never delete this line!
        parent::ApplyChanges();

        //Check runlevel
        if (IPS_GetKernelRunlevel() != KR_READY) {
            return;
        }
     
        //Delete all references
        foreach ($this->GetReferenceList() as $referenceID) {
            $this->UnregisterReference($referenceID);
        }

        //Delete all message registrations
       
        foreach ($this->GetMessageList() as $senderID => $messages) {
            foreach ($messages as $message) {
                if ($message == EM_UPDATE) {
                    $this->UnregisterMessage($senderID, EM_UPDATE);
                }
                if ($message == VM_UPDATE) {
                    $this->UnregisterMessage($senderID, VM_UPDATE);
                }
            }
        }
        ########## WebFront options
       
        ########## References and Messages


        //Register references and messages
        $this->SendDebug(__FUNCTION__, 'Referenzen und Nachrichten werden registriert.', 0);
        $this->RegisterReferenceVarIdList(self::ReferenciesList);
       
        
        //------------------------------------------------------------------------------------------
        //Weekly schedule
        $id = @IPS_GetEventIDByName('Wochenplan',$this->InstanceID);
       
       if (!$id || !@IPS_ObjectExists($id))
       {
           $id = IPS_CreateEvent (2); 
           $this->SendDebug(__FUNCTION__, 'Erstelle Wochenplan mit ID: '.$id, 0);
           IPS_SetParent($id, $this->InstanceID);
           IPS_SetPosition($id,20);
           IPS_SetName($id,'Wochenplan');
           IPS_SetIcon($id,'Calendar');
           switch ( $this->ReadPropertyInteger('WeekTimerGroups'))
           {
                 case 0: //Täglich
                     IPS_SetEventScheduleGroup($id, 0, 1);     //Mo (1)
                     IPS_SetEventScheduleGroup($id, 1, 2);     //Di (2)
                     IPS_SetEventScheduleGroup($id, 2, 4);     //Mi (4)
                     IPS_SetEventScheduleGroup($id, 3, 8);     //Do (8)
                     IPS_SetEventScheduleGroup($id, 4, 16);    //Fr (16)
                     IPS_SetEventScheduleGroup($id, 5, 32);    //Sa (32)
                     IPS_SetEventScheduleGroup($id, 6, 64);    //So (64)
                   break;
                case 1:
                    IPS_SetEventScheduleGroup($id, 0, 31);     //Mo - Fr (1 + 2 + 4 + 8 + 16)
                    IPS_SetEventScheduleGroup($id, 1, 96);     //Sa + So (32 + 64)
                   break;
               case 2:
                    IPS_SetEventScheduleGroup($id, 0, 127);    //Mo - SO (1 + 2 + 4 + 8 + 16+ 32 + 64)
                   break;
           }
       }
       
       IPS_SetEventScheduleAction($id,self::WsReleased,'Freigegeben',self::Yellow,self::MODULE_PREFIX . "_WeekTimerAction($this->InstanceID,1);");
       IPS_SetEventScheduleAction($id,self::WsLocked,'Gesperrt',self::DarkBlue,self::MODULE_PREFIX . "_WeekTimerAction($this->InstanceID,2);");
       
       $this->RegisterReference($id);
       $this->RegisterMessage($id, EM_CHANGEACTIVE);
       $this->RegisterMessage($id,EM_CHANGESCHEDULEGROUPPOINT);
       $this->RegisterMessage($id,EM_CHANGETRIGGER);
       $this->HideItemById ($id,false);
        
       
        //Aktuell ermittelte Id des Weektimers merken
        $this->WriteAttributeInteger('WeekTimerPv', $id);
         //------------------------------------------------------------------------------------------

        ########## Links
        $this->WriteAttributeInteger('PvPowerLink',$this->CreateLink ($this->ReadPropertyInteger('IdPvPower'),'Aktuelle PV-Leistung','Intensitiy', 100));
        $this->CreateLink ( $this->ReadPropertyInteger('IdHeatPumpReleaseStatus'),'Status Wärmepumpe','Ok', 110);
      

        //Alle benötigten aktiven Referenzen für die Messagesink anmelden
        $this->RegisterPropertiesUpdateList(self::RegisterReferenciesUpdateList);
        $this->RegisterVariablesUpdateList(self::RegisterVariablesUpdateList);
        ########## Timer

      

        ########## Profile
       

        ########## Misc
      
        $this->HandleOpMode ($this->GetValue('OpMode'));
    }


    public function Destroy()
    {
        //Never delete this line!
        parent::Destroy();

        //Delete profiles
       $this->DeleteProfileList (self::ProfileList);
       IPS_LogMessage( $this->InstanceID,'Destroy Methode ausgeführt.');
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        switch ($Message) {
            case IPS_KERNELSTARTED:
                $this->KernelReady();
                break;

            case VM_UPDATE:

                //$Data[0] = actual value
                //$Data[1] = value changed
                //$Data[2] = last value
                //$Data[3] = timestamp actual value
                //$Data[4] = timestamp value changed
                //$Data[5] = timestamp last value

                //Hier ist "OnChange" ausprogrammiert, d.h. wenn es keine Differenz zm alten Wert gibt, dann Abflug
                if ($Data[1]==0) return;
                $this->SendDebug(__FUNCTION__, 'Wert hat sich auf ' . $Data[0] . ' geändert.', 0);
               
                if ($this->SelectHeatPumpStatus($SenderID))
                {
                    $this->OperateHeatPumpStatus($Data[0]);
                    return; 
                }
                if ($this->SelectExpertSwitch($SenderID))
                {
                    $this->OperateExpertSwitch($SenderID);
                    return;
                }
                if ($this->SelectControlAlive($SenderID))
                {
                    $this->OperateControlAlive($Data[0]);
                    return; 
                }
                if ($this->SelectPvPower($SenderID))
                {
                    $this->OperatePvPower($Data[0]);
                    return; 
                }
                break;

            //case EM_UPDATE:
            case EM_CHANGEACTIVE:
            case EM_CHANGESCHEDULEGROUPPOINT:
            case EM_CHANGETRIGGER:

                //$Data[0] = last run
                //$Data[1] = next run
                
                //Weekly schedule
                $this->SendDebug(__FUNCTION__, 'Trigger durch'.$Message.'.', 0);
                $this->SendDebug(__FUNCTION__, 'Data[] = ' . json_encode($Data). ' ID: '. $SenderID, 0);
                $this->TriggerAction();
             
                break;
        }
    }

    //----------------------------------------------------------------------------------------------------------------------------
    //Methoden für MessageSink
    private function SelectHeatPumpStatus(int $sender) : bool
    {
        $id = $this->GetIDForIdent('HeatPumpStatus');
        if (!$this->IsValidId($id)) return false;
        if ($id != $sender) return false;
        return true;
    }
    private function SelectExpertSwitch(int $sender) : bool
    {
        $id = $this->ReadPropertyInteger('ExpertModeID');
        if (!$this->IsValidId($id)) return false;
        if ($id != $sender) return false;
        return true;
    }

    private function SelectControlAlive(int $sender) : bool
    {
        $id = $this->ReadPropertyInteger('IdControlAlive');
        if (!$this->IsValidId($id)) return false;
        if ($id != $sender) return false;
        return true;
    }

    private function SelectPvPower(int $sender) : bool
    {
       $id = $this->ReadPropertyInteger('IdPvPower');
       if (!$this->IsValidId($id)) return false;
       if ($id != $sender) return false;
       return true;
    }

    
    private function OperateExpertSwitch(int $id) : void
    {
        $this->HandleExpertSwitch($id,self::ExpertHideList,self::ExpertLockList);
    }

    private function OperateControlAlive(bool $value) : void
    {
        if ($value)
        {
            $this->SendOpMode($this->GetValue('OpMode'));
            $this->SetHeatPumpStatus();
        }
    }

    private function OperatePvPower(float $value) : void
    {
      $this->SetHeatPumpStatus();
    }

    //----------------------------------------------------------------------------------------------------------------------------



   public function WeekTimerAction (int $action) : void
   {
       $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
       $this->WriteAttributeInteger('WeekTimerStatus',$action);
       $this->SetHeatPumpStatus();
   }

   public function UpdateTimer() : void
   {
       $this->StopBoostTimer();
       $this->SetHeatPumpStatus();
   }

  
   private function TriggerAction(): void
   {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        $weekTimerId = $this->ReadAttributeInteger('WeekTimerPv');
        if (!$this->ValidateEventPlan($weekTimerId )) $action = 0;
        else $action = $this->GetWeekTimerAction($weekTimerId);
        $this->WriteAttributeInteger('WeekTimerStatus',$action);
        $this->SetHeatPumpStatus();
   }

   private function SetHeatPumpStatus(): int
   {
       $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
       
       $status = self::HpRequested;

       switch ($this->GetValue('OpMode'))
       {
           case self::Aus:
                $status = self::HpOff;
                $this->StopBoostTimer();
                break;
           case self::Manuell:
                $this->StopBoostTimer();
                break;
           default:
                if ($this->GetValue('BoostMode'))
                {
                    $status = self::HpBoostMode; 
                }
                else if  ($this->ReadAttributeInteger('WeekTimerStatus') == self::WsLocked)
                {
                     $status = self::HpNightLock; 
                }
                else if ($this->IsPvPowerLocked())
                {
                     $status = self::HpPvLimit;
                }
           
       }

       $this->SetValue('HeatPumpRequest',$status); 
       $this-> OperateHeatPumpStatus($status);
       return  $status;
   }

   private function IsPvPowerLocked(): bool
   {
      $power = $this->GetPvPower();
      if ($power < 0) return false;
      $limit =  $this->GetValue('HeatPumpReleasePower');
      if ($power >= $limit ) return false;
      return ($power < $limit - $this->ReadPropertyFloat('PvLimitHysteresis'));
   }

   private function GetPvPower(): float
   {
       $id=  $this->ReadPropertyInteger('IdPvPower');
       if  (!$this->IsValidId($id)) return -1.0;
       return GetValueFloat($id);
   }

   private function OperateHeatPumpStatus(int $status): void
   {
       $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);

       if ($this->GetValue('HeatPumpRequest') == $status) return; 

       switch ($value)
       {
            case self::HpOff:
                $this->SendHeatPumpRelease(false);
                $this->SendHeatPumpNightLock(false);
                break;
            case self::HpPvLimit:
                $this->SendHeatPumpRelease(false);
                $this->SendHeatPumpNightLock(false);
                break;
            case self::HpNightLock:
                $this->SendHeatPumpRelease(false);
                $this->SendHeatPumpNightLock(true);
                break;
            case self::HpBoostMode:
                 $this->SendHeatPumpRelease(true);
                 $this->SendHeatPumpNightLock(false);
                break;
            case self::HpRequested:
                $this->SendHeatPumpRelease(true);
                $this->SendHeatPumpNightLock(false);
                break;
       }
   }


   private function SendOpMode(int $value): void
   {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        $id= $this->ReadPropertyInteger('IdOpModeSend');
        if ($id>0) RequestAction($id, $value);
   }

   private function SendHeatPumpRelease(bool $value): void
   {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        $id= $this->ReadPropertyInteger('IdHeatPumpRelease');
        if ($id>0) RequestAction($id, $value);
   }

    private function SendHeatPumpNightLock(bool $value): void
   {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        $id= $this->ReadPropertyInteger('IdHeatingPumpNightLock');
        if ($id>0) RequestAction($id, $value);
   }

 
   private function StopBoostTimer () : void
   {
       $this->SetTimerInterval('HPCTRL_BoostTimer',0);
       $this->SetValue('BoostMode',false);
   }

   private function StartBoostTimer () : void
   {
        $this->SetTimerInterval('HPCTRL_BoostTimer', $this->ReadPropertyInteger("BoostTime") * 1000 *60);
   }

    #################### Request action

   public function RequestAction($Ident, $Value)
   {
          switch($Ident) {
            case "OpMode":
               $this->SetValue($Ident, $Value);
               $this->HandleOpMode ($Value);
               break;
            case "BoostMode":
               $this->SetValue($Ident, $Value);
               if ($this->SetHeatPumpStatus() == self::HpBoostMode) $this->StartBoostTimer();
               break;
            case "HeatPumpReleasePower":
               $this->SetValue($Ident, $Value);
               $this->SetHeatPumpStatus();
               break;
       }
   }

   private function HandleOpMode (int $opmode) :void
   {
       $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
       $hide=true;

        switch($opmode) {
           case self::Aus:         //Aus 
           case self::Manuell:     //Handbetrieb
                $this->SetHeatPumpStatus ();
               break;
           case self::Automatik:   //Automatikbetrieb
               $hide=false;
               $this->TriggerAction(); 
               break;
           default:
        }

        $this->HideItemById ($this->ReadAttributeInteger('WeekTimerPv'),$hide);
        $this->HideItemById ($this->ReadAttributeInteger('PvPowerLink'),$hide);
        $this->HideItem ('BoostMode',$hide);
        $this->HideItem ('HeatPumpRequest',$hide);
        $this->SendOpMode($opmode);
       
   }
}

