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

    private const ProfileList = 'WeekTimerStatus,OpMode,OpModeActive,AdaptRoomTemperature,IgnoreThermostat';
    private const RegisterList = 'WeekTimerStatus';

    private const Aus = 0;
    private const Manuell = 1;
    private const Automatik = 2;

    private const HeatUndef = 0;
    private const HeatOff = 1;
    private const HeatOn = 2;
    private const HeatOnReduced = 3;

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
         $this->RegisterAttributeString('LinkList', "IdRoomThermostat,IdRoomTemperature,IdHeatingPump,IdMixerPosition,IdSetHeat,IdActHeat");
         $this->RegisterAttributeString('SendList', "IdOpModeSend,IdAdaptRoomTemperatureSend");
         $this->RegisterAttributeString('ExpertListHide',"OpModeActive");
         $this->RegisterAttributeString('ExpertListLock',"");

         $this->RegisterAttributeBoolean('RecurseFlag',false);

        $this->DeleteProfileList (self::ProfileList);
       
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
           IPS_SetVariableProfileAssociation($profileName, 0, "Inaktiv", "", self::Transparent);
           IPS_SetVariableProfileAssociation($profileName, 1, "Nicht Heizen", "", self::Yellow);
           IPS_SetVariableProfileAssociation($profileName, 2, "Heizen", "", self::Green);
           IPS_SetVariableProfileAssociation($profileName, 3, "Absenken", "", self::Blue);
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


        $this->RegisterVariableIds($this->ReadAttributeString('LinkList'));
        //Attribute mit Ids von Links anlegen
        $this->RegisterLinkIds($this->ReadAttributeString('LinkList'));

        $this->RegisterVariableIds($this->ReadAttributeString('SendList'));
        $this->RegisterPropertyInteger('ExpertModeID', 0);
        $this->RegisterPropertyInteger('IdControlAlive',0);
        $this->RegisterPropertyInteger('WeekTimerGroups',0);
        $this->RegisterPropertyBoolean('UseWeekTimer',0);
        $this->RegisterPropertyFloat('OffsetTemperature',0);
       
        $this->RegisterAttributeInteger('WeekTimer', 0);
        
        //Benötige Anmeldudngen für MessageSing durchführen
        foreach ( $this->GetArrayFromString(self::RegisterList) as $item) {
              $this->RegisterMessage($this->GetIDForIdent($item),VM_UPDATE);
        }
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
        

         $this->WriteAttributeString('ExpertListHide',"OpModeActive");
         $this->WriteAttributeString('ExpertListLock',"");

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
            IPS_SetEventScheduleAction($id,1,"Aus",self::DarkBlue,self::MODULE_PREFIX . "_WeekTimerAction($this->InstanceID,1);");
            IPS_SetEventScheduleAction($id,2,"Normal",self::Yellow,self::MODULE_PREFIX . "_WeekTimerAction($this->InstanceID,2);");
            IPS_SetEventScheduleAction($id,3,"Absenken",self::DarkGreen,self::MODULE_PREFIX . "_WeekTimerAction($this->InstanceID,3);");
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
        $this->WriteAttributeInteger('WeekTimer', $id);
       
        $this->RegisterStatusUpdate('ExpertModeID');
        $this->RegisterStatusUpdate('IdControlAlive');

        //Benötige Anmeldudngen für MessageSing durchführen
        foreach ( $this->GetArrayFromString(self::RegisterList) as $item) {
              $this->RegisterMessage($this->GetIDForIdent($item),VM_UPDATE);
        }

        ########## Links

        $this->WriteAttributeInteger('IdRoomThermostat',$this->CreateLink ( $this->ReadPropertyInteger('IdRoomThermostat'),'Raumthermostat','Flame', 100));
        $this->WriteAttributeInteger('IdRoomTemperature',$this->CreateLink ( $this->ReadPropertyInteger('IdRoomTemperature'),'Raumtemperatur','Temperature', 110));
        $this->WriteAttributeInteger('IdHeatingPump',$this->CreateLink ( $this->ReadPropertyInteger('IdHeatingPump'),'Heizungspumpe','TurnRight', 120));
        $this->WriteAttributeInteger('IdMixerPosition',$this->CreateLink ( $this->ReadPropertyInteger('IdMixerPosition'),'Mischerposition','Intensity', 140));
        $this->WriteAttributeInteger('IdSetHeat',$this->CreateLink ( $this->ReadPropertyInteger('IdSetHeat'),'Vorlauftemperatur Sollwert','Temperature',160));
        $this->WriteAttributeInteger('IdActHeat',$this->CreateLink ( $this->ReadPropertyInteger('IdActHeat'),'Vorlauftemperatur Istwert','Temperature',180));

        ########## Timer

      

        ########## Misc
        $this->SendDebug(__FUNCTION__, 'Profile werden angepasst.', 0);
        $profileName =  $this->CreateProfileName('OpMode');
      
        if (IPS_VariableProfileExists($profileName)) {
            if (!$this->ReadPropertyBoolean('UseWeekTimer') && ($this->ReadAttributeInteger('IdRoomThermostat')==0))
            {
                $status = IPS_SetVariableProfileValues($profileName, 0, 1, 0);
                IPS_SetVariableProfileAssociation($profileName, 0, "Aus", "", self::Transparent);
                IPS_SetVariableProfileAssociation($profileName, 1, "Manuell", "", self::Yellow);
                IPS_SetVariableProfileAssociation($profileName, 2, "", "", self::Transparent);//Leerer Name und Icon löschen den Werteeintrag 
            }
            else {
                IPS_SetVariableProfileValues($profileName, 0, 2, 0);
                IPS_SetVariableProfileAssociation($profileName, 0, "Aus", "", self::Transparent);
                IPS_SetVariableProfileAssociation($profileName, 1, "Manuell", "", self::Yellow);
                IPS_SetVariableProfileAssociation($profileName, 2, "Automatik", "", self::Green);
            }

        }
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
                $this->SendDebug(__FUNCTION__, 'Wert sich auf ' . $Data[0] . ' geändert.', 0);
               
                //Wochenplan Status
                if ($this->GetIDForIdent('WeekTimerStatus') == $SenderID ) {   
                    $this->SetWeekTimerStatus($Data[0]);
                }
                else if ($this->ReadPropertyInteger('ExpertModeID') == $SenderID)
                {
                    $this->HandleExpertSwitch($SenderID,$this->ReadAttributeString('ExpertListHide'),$this->ReadAttributeString('ExpertListLock'));
                }
                else if ($this->ReadPropertyInteger('IdControlAlive') == $SenderID)
                {
                   if (!$Data[0]) return;
                    $this->SendOpMode($this->GetValue('OpModeActive'));
                    $this->SendAdaptRoomTemperature ($this->GetValue('AdaptRoomTemperature'));
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

   private function OperateIgnoreThermostat(bool $value) : void
   {
       $id = $this->ReadAttributeInteger('IdRoomThermostat');
       if ($id==0) return;
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
     
       $this->SendOpMode($this->GetControlOpMode($value));
   }

   private function GetControlOpMode(int $value) : int
   {
       switch ($value)
       {
            case self::HeatOff:
                return self::Aus;
            case self::HeatOn:
            case self::HeatOnReduced:
            case self::HeatUndef:
                if (($this->ReadAttributeInteger('IdRoomThermostat')>0) && $this->GetValue('IgnoreThermostat'))
                {
                     return self::Manuell;
                }
                return self::Automatik;
       }
       return $value;
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

        $actValue = $value;
        switch ($this->GetValue('OpMode'))
           {
               case self::Aus:
               case self::Manuell:
                    $actValue = 0.0;
                    break;
               case self::Automatik:
                    $actValue = 0.0;
                    switch ($this->GetValue('WeekTimerStatus'))
                    {
                        case self::HeatOnReduced:
                            $actValue = $this->ReadPropertyFloat('OffsetTemperature');
                            break;
                    }
                    break;
           }

        if ($id>0) RequestAction($id, $value);
        
   }

    #################### Request action

   public function RequestAction($Ident, $Value)
   {
          switch($Ident) {
           case "OpMode":
               $this->SetValue($Ident, $Value);
               $this->HandleOpMode ($Value);
               break;
           case "WeekTimerStatus":
                $this->SetValue($Ident, $Value);
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
               $this->HideItemById ($this->GetIDForIdent('IgnoreThermostat'),$this->ReadAttributeInteger('IdRoomThermostat')==0);
               $this->HideItemById ($this->ReadAttributeInteger('IdRoomThermostat'),$this->GetValue('IgnoreThermostat'));
               $this->TriggerAction(); 
               $opmode = $this->GetControlOpMode($this->GetValue('WeekTimerStatus'));
               break;
           default:
        }
        $this->SendOpMode($opmode);
   }
}