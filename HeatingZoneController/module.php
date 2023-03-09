<?php

/**
 * @project       HeatingZoneController
 * @file          module.php
 * @author        Alfred Schorn
 * @copyright     2023 Alfred Schorn
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 */

/** @noinspection PhpUnhandledExceptionInspection */
/** @noinspection PhpUnused */

declare(strict_types=1);

include_once __DIR__ . '../../libs/RBH_ModuleFunctions.php';

class HeatingZoneController extends IPSModule
{
    use RBH_ModuleFunctions;


    //Constants
    private const LIBRARY_GUID = '{016A5116-600D-1C0B-9CA8-20F59140AD40}';
    private const MODULE_NAME = 'HeatingZoneController';
    private const MODULE_PREFIX = 'HZCTRL';
    private const MODULE_VERSION = '1.0, 14.02.2023';
    private const MINIMUM_DELAY_MILLISECONDS = 100;

    private const ProfileList =                     'WeekTimerStatus,OpMode,OpModeActive,AdaptRoomTemperature,IgnoreThermostat,BoostMode';
    private const RegisterVariablesUpdateList =     'WeekTimerStatus';
    private const RegisterReferenciesUpdateList =   'ExpertModeID,IdControlAlive,IdRoomThermostat';
    private const ReferenciesList =                 'IdRoomThermostat,IdRoomTemperature,IdHeatingPump,IdMixerPosition,IdSetHeat,IdActHeat,IdOpModeSend,IdAdaptRoomTemperatureSend';
    private const ExpertLockList =                  '';
    private const ExpertHideList =                  'OpModeActive';
   
    private const Aus = 0;
    private const Manuell = 1;
    private const Automatik = 2;

    private const HeatUndef = 0;
    private const HeatOff = 1;
    private const HeatOn = 2;
    private const HeatOnReduced = 3;
    private const HeatOnBoost = 4;

    private const  Transparent = 0xffffff00;
    private const  Red = 0xFF0000;
    private const  Yellow = 0xFFFF00;
    private const  Green=0x00FF00;
    private const  Blue=0x0000FF;

    private const DarkGreen = 0x24F065;
    private const DarkBlue  = 0x0D0FE4;
  
    public function Create()
    {
        //Never delete this line!
        parent::Create();

        ########## Properties
        $this->DeleteProfileList (self::ProfileList);
       
        $this->RegisterPropertyInteger('ExpertModeID', 0);
        $this->RegisterPropertyInteger('IdControlAlive',0);
        $this->RegisterPropertyInteger('WeekTimerGroups',0);
        $this->RegisterPropertyBoolean('UseWeekTimer',0);
        $this->RegisterPropertyFloat('OffsetTemperature',0);
        $this->RegisterPropertyFloat('BoostTemperature',0);
        $this->RegisterPropertyBoolean('UseBoostMode',0);
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


        $variable = 'OpModeActive';
        $profileName =  $this->CreateProfileName($variable);
        if (!IPS_VariableProfileExists($profileName)) {
            IPS_CreateVariableProfile($profileName, 1);
            IPS_SetVariableProfileValues($profileName, 0, 2, 0);
            IPS_SetVariableProfileIcon($profileName, "Information");
            IPS_SetVariableProfileAssociation($profileName, 0, "Aus", "", self::Red);
            IPS_SetVariableProfileAssociation($profileName, 1, "Ein", "", self::Yellow);
            IPS_SetVariableProfileAssociation($profileName, 2, "Automatik", "", self::Green);
        }
        $this->RegisterVariableInteger($variable, $this->Translate('Active Operation Mode'),$profileName, 15);
       

         //WeekTimerStatus
        $variable = 'WeekTimerStatus';
        $profileName = $this->CreateProfileName($variable);
        if (!IPS_VariableProfileExists($profileName)) {
           IPS_CreateVariableProfile($profileName, 1);
           IPS_SetVariableProfileIcon($profileName, "Ok");
           IPS_SetVariableProfileAssociation($profileName, self::HeatUndef, $this->GetHeatingStatusText(self::HeatUndef), "", self::Transparent);
           IPS_SetVariableProfileAssociation($profileName, self::HeatOff, $this->GetHeatingStatusText(self::HeatOff), "", self::Yellow);
           IPS_SetVariableProfileAssociation($profileName, self::HeatOn,  $this->GetHeatingStatusText(self::HeatOn), "", self::Green);
           IPS_SetVariableProfileAssociation($profileName, self::HeatOnReduced,  $this->GetHeatingStatusText(self::HeatOnReduced), "", self::Blue);
           IPS_SetVariableProfileAssociation($profileName, self::HeatOnBoost,  $this->GetHeatingStatusText(self::HeatOnBoost), "", self::Red);
        }
        $this->RegisterVariableInteger($variable, $this->Translate('Week Timer Status'), $profileName, 30);

        //Adapt Room Temperatur
        $variable = 'AdaptRoomTemperature';
        $profileName = $this->CreateProfileName($variable);
        if (!IPS_VariableProfileExists($profileName)) {
            IPS_CreateVariableProfile($profileName, 2);
            IPS_SetVariableProfileIcon($profileName, "Temperature");
            IPS_SetVariableProfileValues($profileName, -10, +10, 1);
            IPS_SetVariableProfileDigits($profileName, 0);
            IPS_SetVariableProfileText($profileName,"","°C");

        }
        $this->RegisterVariableFloat($variable, $this->Translate('Adapt Room Temperature'), $profileName, 60);
        $this->EnableAction($variable);

        $variable = 'IgnoreThermostat';
        $profileName = $this->CreateProfileName($variable);
        if (!IPS_VariableProfileExists($profileName)) {
            IPS_CreateVariableProfile($profileName, 0);
            IPS_SetVariableProfileIcon($profileName, "Ok");
            IPS_SetVariableProfileAssociation($profileName, false, "Nein", "", self::Transparent);
            IPS_SetVariableProfileAssociation($profileName, true, "Ja", "", self::Yellow);
        }
        $this->RegisterVariableBoolean($variable, $this->Translate('Ignore Thermostat'),$profileName, 65);
        $this->EnableAction($variable);


        $variable = 'BoostMode';
        $profileName = $this->CreateProfileName($variable);
        if (!IPS_VariableProfileExists($profileName)) {
            IPS_CreateVariableProfile($profileName, 0);
            IPS_SetVariableProfileIcon($profileName, "Ok");
            IPS_SetVariableProfileAssociation($profileName, false, "Nein", "", self::Transparent);
            IPS_SetVariableProfileAssociation($profileName, true, "Ja", "", self::Yellow);
        }
        $this->RegisterVariableBoolean($variable, $this->Translate('Boost Mode'),$profileName, 70);
        $this->EnableAction($variable);
     
        //Alle in der "form.json" definierten Variablenreferenzen registrieren
        $this->RegisterVariableIds(self::ReferenciesList);
        $this->RegisterAttributeInteger('WeekTimer', 0);
        $this->RegisterAttributeInteger('IdRoomThermostat', 0);
       
        ########## Timer
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
        
        //------------------------------------------------------------------------------------------
        //Weekly schedule
        $id = @IPS_GetEventIDByName('Wochenplan',$this->InstanceID);
        if ($this->ReadPropertyBoolean('UseWeekTimer'))
        {
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
            IPS_SetEventScheduleAction($id,self::HeatOff,$this->GetHeatingStatusText(1),self::DarkBlue,self::MODULE_PREFIX . "_WeekTimerAction($this->InstanceID,1);");
            IPS_SetEventScheduleAction($id,self::HeatOn,$this->GetHeatingStatusText(2),self::Yellow,self::MODULE_PREFIX . "_WeekTimerAction($this->InstanceID,2);");
            IPS_SetEventScheduleAction($id,self::HeatOnReduced,$this->GetHeatingStatusText(3),self::DarkGreen,self::MODULE_PREFIX . "_WeekTimerAction($this->InstanceID,3);");
            IPS_SetEventScheduleAction($id,self::HeatOnBoost,$this->GetHeatingStatusText(4),self::Red,self::MODULE_PREFIX . "_WeekTimerAction($this->InstanceID,4);");
            
            $this->SetHeatingStatusProfile();
            
            $this->RegisterReference($id);
            $this->RegisterMessage($id, EM_CHANGEACTIVE);
            $this->RegisterMessage($id,EM_CHANGESCHEDULEGROUPPOINT);
            $this->RegisterMessage($id,EM_CHANGETRIGGER);
            $this->HideItemById ($id,false);
        }
        else 
        {
             if (is_int($id)) $this->HideItemById ($id,true);
        }
        //Aktuell ermittelte Id des Weektimers merken
        $this->WriteAttributeInteger('WeekTimer', $id);
         //------------------------------------------------------------------------------------------

        ########## Links

        $this->WriteAttributeInteger('IdRoomThermostat',$this->CreateLink ($this->ReadPropertyInteger('IdRoomThermostat'),'Raumthermostat','Flame', 100));
        $this->CreateLink ( $this->ReadPropertyInteger('IdRoomTemperature'),'Raumtemperatur','Temperature', 110);
        $this->CreateLink ( $this->ReadPropertyInteger('IdHeatingPump'),'Heizungspumpe','TurnRight', 120);
        $this->CreateLink ( $this->ReadPropertyInteger('IdMixerPosition'),'Mischerposition','Intensity', 140);
        $this->CreateLink ( $this->ReadPropertyInteger('IdSetHeat'),'Vorlauftemperatur Sollwert','Temperature',160);
        $this->CreateLink ( $this->ReadPropertyInteger('IdActHeat'),'Vorlauftemperatur Istwert','Temperature',180);

        //Alle benötigten aktiven Referenzen für die Messagesink anmelden
        $this->RegisterPropertiesUpdateList(self::RegisterReferenciesUpdateList);
        $this->RegisterVariablesUpdateList(self::RegisterVariablesUpdateList);
        
        ########## Timer

      

        ########## Profile
        $this->SendDebug(__FUNCTION__, 'Profile werden angepasst.', 0);
        $profileName =  $this->CreateProfileName('OpMode');
      
        if (IPS_VariableProfileExists($profileName)) {
            if (!$this->ReadPropertyBoolean('UseWeekTimer') && ($this->ReadPropertyInteger('IdRoomThermostat')==0))
            {
                $status = IPS_SetVariableProfileValues($profileName, 0, 1, 0);
                IPS_SetVariableProfileAssociation($profileName, 0, "Aus", "", self::Red);
                IPS_SetVariableProfileAssociation($profileName, 1, "Manuell", "", self::Yellow);
                IPS_SetVariableProfileAssociation($profileName, 2, "", "", self::Transparent);//Leerer Name und Icon löschen den Werteeintrag 
            }
            else {
                IPS_SetVariableProfileValues($profileName, 0, 2, 0);
                IPS_SetVariableProfileAssociation($profileName, 0, "Aus", "", self::Red);
                IPS_SetVariableProfileAssociation($profileName, 1, "Manuell", "", self::Yellow);
                IPS_SetVariableProfileAssociation($profileName, 2, "Automatik", "", self::Green);
            }

        }

        ########## Misc
        $this->HideItemById ($this->GetIDForIdent('BoostMode'),!$this->ReadPropertyBoolean('UseBoostMode'));
        $this->HandleOpMode ($this->GetValue('OpMode'));
    }

    private function GetHeatingStatusText(int $status) : string
    {
       switch ($status)
       {
            case self::HeatOff:
                return "Nicht Heizen";
            case self::HeatOn:
                return "Heizen 21°C";
            case self::HeatOnReduced:
                return "Absenken ". (21.0+$this->ReadPropertyFloat('OffsetTemperature'))."°C";
            case self::HeatOnBoost:
                return "Heizen ". (21.0+$this->ReadPropertyFloat('BoostTemperature'))."°C";
            case self::HeatUndef:
                return "Inaktiv";
       }
    }

    private function GetHeatingStatusTextDyn(int $status) : string
    {
       $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
       $boostMode = $this->GetValue('BoostMode');
       $boostTemperature = $this->GetBoostTemperature();
       $this->SendDebug(__FUNCTION__, 'BoostMode:'.$boostMode. 'BoostTemp:'.$boostTemperature, 0);
       switch ($status)
       {
            case self::HeatOff:
                return "Nicht Heizen";
            case self::HeatOn:
                if ($boostMode)
                {
                   return "Heizen ". (21.0+$boostTemperature)."°C";
                }
                else 
                {
                     return "Heizen 21°C";
                }
            case self::HeatOnReduced:
                if ($boostMode)
                {
                   return "Heizen ". (21.0+$boostTemperature)."°C";
                }
                else 
                {
                     return "Absenken ". (21.0+$this->ReadPropertyFloat('OffsetTemperature'))."°C";
                }
            case self::HeatOnBoost:
                if ($boostMode)
                {
                   return "Heizen ". (21.0+$boostTemperature)."°C";
                }
                else 
                {
                     return "Heizen 21°C";
                }
            case self::HeatUndef:
                return "Inaktiv";
       }
    }

    private function SetHeatingStatusProfile () : void
    {
         $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
         $variable = 'WeekTimerStatus';
         $profileName = $this->CreateProfileName($variable);

         IPS_SetVariableProfileAssociation($profileName, self::HeatUndef, $this->GetHeatingStatusTextDyn(self::HeatUndef), "", self::Transparent);
         IPS_SetVariableProfileAssociation($profileName, self::HeatOff, $this->GetHeatingStatusTextDyn(self::HeatOff), "", self::Yellow);
         IPS_SetVariableProfileAssociation($profileName, self::HeatOn,  $this->GetHeatingStatusTextDyn(self::HeatOn), "", self::Green);
         IPS_SetVariableProfileAssociation($profileName, self::HeatOnReduced,  $this->GetHeatingStatusTextDyn(self::HeatOnReduced), "", self::Blue);
         IPS_SetVariableProfileAssociation($profileName, self::HeatOnBoost,  $this->GetHeatingStatusTextDyn(self::HeatOnBoost), "", self::Red);
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
               
                if ($this->SelectWeekTimerStatus($SenderID))
                {
                    $this->OperateWeekTimerStatus($Data[0]);
                    return; 
                }
                if ($this->SelectExpertSwitch($SenderID))
                {
                    $this->OperateExpertSwitch($Data[0]);
                    return;
                }
                if ($this->SelectControlAlive($SenderID))
                {
                    $this->OperatControlAlive($Data[0]);
                    return; 
                }
                if ($this->SelectRoomThermostat($SenderID))
                {
                    $this->OperateRoomThermostat($Data[0]);
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

     private function SelectWeekTimerStatus(int $sender) : bool
    {
        $id = $this->GetIDForIdent('WeekTimerStatus');
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

    private function SelectRoomThermostat(int $sender) : bool
    {
       $id = $this->ReadPropertyInteger('IdRoomThermostat');
       if (!$this->IsValidId($id)) return false;
       if ($id != $sender) return false;
       return true;
    }

    private function OperateWeekTimerStatus(int $value) : void
    {
        $this->SetWeekTimerStatus($value);
        $this->SendAdaptRoomTemperature ($this->GetValue('AdaptRoomTemperature'));
    }

    private function OperateExpertSwitch(bool $value) : void
    {
        $this->HandleExpertSwitch($sender,self::ExpertHideList,self::ExpertLockList);
    }

    private function OperatControlAlive(bool $value) : void
    {
        if ($value)
        {
            $this->SendOpMode($this->GetValue('OpModeActive'));
            $this->SendAdaptRoomTemperature ($this->GetValue('AdaptRoomTemperature'));
        }
    }

    private function OperateRoomThermostat(bool $value) : void
    {
       $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
       if ($value)
       {
            $this->SetValue("BoostMode",false);
       }
       $this->SetHeatingStatusProfile ();
       $this->SendAdaptRoomTemperature ($this->GetValue('AdaptRoomTemperature'));
    }

   private function OperateIgnoreThermostat(bool $value) : void
   {
       $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
       $id = $this->ReadPropertyInteger('IdRoomThermostat');
       if (!$this->IsValidId($id)) return;
       $this->HideItemById($id,$value);
       $opmode = $this->GetControlOpMode($this->GetValue('WeekTimerStatus'));
       $this->SendOpMode($opmode);
   }

   public function WeekTimerAction (int $action) : void
   {
       if (!$this->ReadPropertyBoolean('UseWeekTimer')) return;
       $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
       $this->SetValue('WeekTimerStatus',$action); 
   }

   private function TriggerAction(): void
   {
        if (!$this->ReadPropertyBoolean('UseWeekTimer')) return;
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        $weekTimerId = $this->ReadAttributeInteger('WeekTimer');
        if (!$this->ValidateEventPlan($weekTimerId )) $actionID = 0;
        else $actionID = $this->GetWeekTimerAction($weekTimerId );
        $this->SetValue('WeekTimerStatus',$actionID); 
   }

   private function SetWeekTimerStatus(int $value): void
   {
       $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
       switch ($this->GetValue('OpMode'))
       {
           case self::Aus:
                return;
           case self::Manuell:
                return;
       }
       $this->SetBoostMode($value);
       $this->SendOpMode($this->GetControlOpMode($value));
   }

   private function SetBoostMode(int $value) : void
   {
       $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
       switch ($value)
       {
            case self::HeatOff:
            case self::HeatOn:
            case self::HeatOnReduced:
            case self::HeatUndef:
                $this->SetValue('BoostMode',false);
                break;
            case self::HeatOnBoost:
                $this->SetValue('BoostMode',!$this->IsTemperatureOk());
                break;

       }
   }

   private function GetControlOpMode(int $value) : int
   {
       switch ($value)
       {
            case self::HeatOff:
                return self::Aus;
            case self::HeatOn:
            case self::HeatOnReduced:
            case self::HeatOnBoost:
            case self::HeatUndef:
                if (($this->ReadPropertyInteger('IdRoomThermostat')>0) && $this->GetValue('IgnoreThermostat'))
                {
                     return self::Manuell;
                }
                else if ($this->ReadPropertyInteger('IdRoomThermostat')==0)
                {
                    return self::Manuell;
                }
                return self::Automatik;
       }
       return $value;
   }

   private function IsTemperatureOk () : bool
   {
       $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
       $id = $this->ReadPropertyInteger('IdRoomThermostat');
       if (!$this->IsValidId($id)) return false;
       if ($this->GetValue('IgnoreThermostat')) return false;
       return GetValueBoolean($id);
       
   }

   private function SendOpMode(int $value): void
   {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        $this->SetValue('OpModeActive',$value);
        $id= $this->ReadPropertyInteger('IdOpModeSend');
        if ($id>0) RequestAction($id, $value);
   }

   private function SendAdaptRoomTemperature (float $value): void
   {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        $id= $this->ReadPropertyInteger('IdAdaptRoomTemperatureSend');

        $offset = 0.0;
        switch ($this->GetValue('OpMode'))
        {
            case self::Aus:
                $this->SetValue('BoostMode',false);
                break;
            case self::Manuell:
                $offset = $this->GetBoostTemperature ();
                break;
            case self::Automatik:
                 $offset = $this->GetBoostTemperature ();
                 switch ($this->GetValue('WeekTimerStatus'))
                 {
                     case self::HeatOnReduced:
                         $offset = $this->ReadPropertyFloat('OffsetTemperature');
                         break;
                     case self::HeatOnBoost: 
                         break;
                 }
                 break;
        }

        if ($id>0) RequestAction($id, $value+$offset);
   }

   private function GetBoostTemperature () : float
   {
      if (!$this->GetValue('BoostMode')) return 0.0;
      if ($this->IsTemperatureOk()) return 0.0;
      return $this->ReadPropertyFloat('BoostTemperature');
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
                $this->SetValue($Ident,$Value);
                $this->SendAdaptRoomTemperature ($this->GetValue('AdaptRoomTemperature'));
                $this->SetHeatingStatusProfile();
               break;
           case "AdaptRoomTemperature":
                $this->SetValue($Ident, $Value);
                $this->SendAdaptRoomTemperature($Value);
               break;
           case "IgnoreThermostat":
               $this->SetValue($Ident, $Value);
               $this->OperateIgnoreThermostat($Value);
               break;
       }
   }

   private function HandleOpMode (int $opmode)
   {
       $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
       $hide=true;

        switch($opmode) {
           case self::Aus:         //Aus 
           case self::Manuell:     //Handbetrieb
                $this->HideItemById ( $this->ReadAttributeInteger('IdRoomThermostat'),true);
                $this->HideItemById ( $this->GetIDForIdent('IgnoreThermostat'),true);
                $this->HideItemById ( $this->GetIDForIdent('WeekTimerStatus'),true);
                $this->HideItemById ( $this->ReadAttributeInteger('WeekTimer'),true);
               break;
           case self::Automatik:   //Automatikbetrieb
               $hide= !$this->ReadPropertyBoolean('UseWeekTimer');
               $this->HideItemById ($this->ReadAttributeInteger('WeekTimer'),$hide);
               $this->HideItemById ($this->GetIDForIdent('WeekTimerStatus'),$hide);
               $this->HideItemById ($this->GetIDForIdent('IgnoreThermostat'),$this->ReadPropertyInteger('IdRoomThermostat')==0);
               $this->HideItemById ($this->ReadAttributeInteger('IdRoomThermostat'),$this->GetValue('IgnoreThermostat'));
               $this->TriggerAction(); 
               $opmode = $this->GetControlOpMode($this->GetValue('WeekTimerStatus'));
               break;
           default:
        }
        $this->SendOpMode($opmode);
        $this->SendAdaptRoomTemperature ($this->GetValue('AdaptRoomTemperature'));
   }
}

