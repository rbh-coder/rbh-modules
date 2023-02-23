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

class HeatingZoneController extends IPSModule
{
  
    //Constants
    private const LIBRARY_GUID = '{016A5116-600D-1C0B-9CA8-20F59140AD40}';
    private const MODULE_NAME = 'HeatingZoneController';
    private const MODULE_PREFIX = 'HZCTRL';
    private const MODULE_VERSION = '1.0, 14.02.2023';
    private const MINIMUM_DELAY_MILLISECONDS = 100;

    private const Aus = 0;
    private const Manuell = 1;
    private const Automatik = 2;

    private const HeatUndef = 0;
    private const HeatOff = 1;
    private const HeatOn = 2;

    private const  Transparent = 0xffffff00;
    private const  Red = 0xFF0000;
    private const  Yellow = 0xFFFF00;
    private const  Green=0x00FF00;
    private const  Blue=0x0000FF;
  
    public function Create()
    {
        //Never delete this line!
        parent::Create();

        ########## Properties
         $this->RegisterAttributeString('ProfileList',"WeekTimerStatus,OpMode,AdaptRoomTemperature");
         $this->RegisterAttributeString('LinkList', "WeekTimer,IdRoomThermostat,IdRoomTemperature,IdHeatingPump,IdMixerPosition,IdSetHeat,IdActHeat");
         $this->RegisterAttributeString('SendList', "IdOpModeSend,IdAdaptRoomTemperatureSend");
         $this->RegisterAttributeString('ExpertListHide',"OpModeActive");
         $this->RegisterAttributeString('ExpertListLock',"OpMode");

         $this->RegisterAttributeBoolean('RecurseFlag',false);

        $this->DeleteProfileList ('ProfileList');
       
        ########## Variables

       //Variablen --------------------------------------------------------------------------------------------------------
       
       //OpMode
        $variable = 'OpMode';
        $profileName =  $this->CreateProfileName($variable);
        if (!IPS_VariableProfileExists($profileName)) {
            IPS_CreateVariableProfile($profileName, 1);
            IPS_SetVariableProfileValues($profileName, 0, 2, 0);
            IPS_SetVariableProfileIcon($profileName, "Shutter");
            IPS_SetVariableProfileAssociation($profileName, 0, "Aus", "", self::Transparent);
            IPS_SetVariableProfileAssociation($profileName, 1, "Hand", "", self::Yellow);
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
            IPS_SetVariableProfileAssociation($profileName, 0, "Aus", "", self::Transparent);
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
        }
        $this->RegisterVariableInteger($variable, $this->Translate('Week Timer Status'), $profileName, 30);
        //$this->EnableAction($variable);
        //Variable für Änderungen registrieren
        $this->RegisterMessage($this->GetIDForIdent($variable),VM_UPDATE);

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

        $this->RegisterVariableIds($this->ReadAttributeString('LinkList'));
        //Attribute mit Ids von Links anlegen
        $this->RegisterLinkIds($this->ReadAttributeString('LinkList'));

        $this->RegisterVariableIds($this->ReadAttributeString('SendList'));
        $this->RegisterPropertyInteger('ExpertModeID', 0);
        ########## Timer
    }

    private function RegisterVariableIds(string $itemsString) : void
    {
        foreach (explode(',', $itemsString) as $item) {
            if ($item != "") $this->RegisterPropertyInteger($item, 0);
        }
    }

     private function RegisterLinkIds(string $itemsString) :void
    {
        foreach (explode(',', $itemsString) as $item) {
            $this->RegisterAttributeInteger($item, 0);
        }
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
        
        //Weekly schedule
        $id = $this->ReadPropertyInteger('WeekTimer');
        if ($id != 0 && @IPS_ObjectExists($id)) {
            $this->RegisterReference($id);
            //$this->RegisterMessage($id, EM_UPDATE);
            $this->RegisterMessage($id, EM_CHANGEACTIVE);
            $this->RegisterMessage($id,EM_CHANGESCHEDULEGROUPPOINT);
            $this->RegisterMessage($id,EM_CHANGETRIGGER);
            //IPS_SetEventScript($id,self::MODULE_PREFIX . '_WeekTimerAction($_IPS[\'ACTION\']);');
            //IPS_SetEventScheduleAction(($id,1,"Aus",2420837,self::MODULE_PREFIX . '_WeekTimerAction('.$this->InstanceID.',1);');
             $this->SendDebug(__FUNCTION__, 'Test ID: '.$this->InstanceID, 0);
            IPS_SetEventScheduleAction($id,1,"Aus",2420837,self::MODULE_PREFIX . "_WeekTimerAction($this->InstanceID,1);");
            IPS_SetEventScheduleAction($id,2,"Ein",8560364,self::MODULE_PREFIX . "_WeekTimerAction($this->InstanceID,2);");
            $this->SendDebug(__FUNCTION__, 'IPS_GetEvent: '.json_encode(IPS_GetEvent($id), JSON_PRETTY_PRINT), 0);
        }
        $id = IPS_GetEventIDByName('WeekTimer',$this->InstanceID);
        if (!@IPS_ObjectExists($id))
        {
            $id = IPS_CreateEvent (2); 
            IPS_SetParent($id, $this->InstanceID);
            IPS_SetPosition($id,20);
            IPS_SetName($id,'WeekTimer');
            IPS_SetIcon($id,'Calendar');
            IPS_SetEventScheduleGroup($EreignisID, 0, 31); //Mo - Fr (1 + 2 + 4 + 8 + 16)
            IPS_SetEventScheduleGroup($EreignisID, 1, 96); //Sa + So (32 + 64)
            $this->SendDebug(__FUNCTION__, 'Test ID: '.$this->InstanceID, 0);
            IPS_SetEventScheduleAction($id,1,"Aus",2420837,self::MODULE_PREFIX . "_WeekTimerAction($this->InstanceID,1);");
            IPS_SetEventScheduleAction($id,2,"Ein",8560364,self::MODULE_PREFIX . "_WeekTimerAction($this->InstanceID,2);");
            $this->SendDebug(__FUNCTION__, 'IPS_GetEvent: '.json_encode(IPS_GetEvent($id), JSON_PRETTY_PRINT), 0);
        }
        $this->RegisterReference($id);
        $this->RegisterMessage($id, EM_CHANGEACTIVE);
        $this->RegisterMessage($id,EM_CHANGESCHEDULEGROUPPOINT);
        $this->RegisterMessage($id,EM_CHANGETRIGGER);

        $this->RegisterStatusUpdate('ExpertModeID');
        

        ########## Links

        //Weekly schedule
        $this->WriteAttributeInteger('WeekTimer',$this->CreateLink ( $this->ReadPropertyInteger('WeekTimer'),'Wochenplan','Calendar', 20));
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
            if (($this->ReadAttributeInteger('WeekTimer') == 0) && ($this->ReadAttributeInteger('IdRoomThermostat')==0))
            {
                $status = IPS_SetVariableProfileValues($profileName, 0, 1, 0);
                IPS_SetVariableProfileAssociation($profileName, 0, "Aus", "", self::Transparent);
                IPS_SetVariableProfileAssociation($profileName, 1, "Hand", "", self::Yellow);
                IPS_SetVariableProfileAssociation($profileName, 2, "", "", self::Transparent);//Leerer Name und Icon löschen den Werteeintrag 
            }
            else {
                IPS_SetVariableProfileValues($profileName, 0, 2, 0);
                IPS_SetVariableProfileAssociation($profileName, 0, "Aus", "", self::Transparent);
                IPS_SetVariableProfileAssociation($profileName, 1, "Hand", "", self::Yellow);
                IPS_SetVariableProfileAssociation($profileName, 2, "Automatik", "", self::Green);
            }

        }

        $this->SendDebug(__FUNCTION__, 'GetReferenceList: '.json_encode($this->GetReferenceList(), JSON_PRETTY_PRINT), 0);
        $this->SendDebug(__FUNCTION__, 'GetMessageList: '.json_encode($this->GetMessageList(), JSON_PRETTY_PRINT), 0);
        $this->HandleOpMode ($this->GetValue('OpMode'));
    }

    private function CreateLink (int $targetID,string $name,string $iconName, int $position) :int
    {
        $linkID = @IPS_GetLinkIDByName($name, $this->InstanceID);
        if ($targetID != 0 && @IPS_ObjectExists($targetID)) {
            //Check for existing link
            if (!is_int($linkID) && !$linkID) {
                $linkID = IPS_CreateLink();
            }
            else {
	            IPS_DeleteLink($linkID);
                $linkID = IPS_CreateLink();
            }

            IPS_SetParent($linkID, $this->InstanceID);
            IPS_SetPosition($linkID,$position);
            IPS_SetName($linkID, $name);
            IPS_SetIcon($linkID,$iconName);
            IPS_SetLinkTargetID($linkID, $targetID);
            return $linkID;

        } else {
            if (is_int($linkID)) {
                 IPS_DeleteLink($linkID);
            }
        }
         return 0;
    }

    //Methode Registriert Variable für die MessageSink, soferne dieser in der Modul-Form aktiviert ist
    private function RegisterStatusUpdate(string $statusName)
    {
        $id= $this->ReadPropertyInteger($statusName);
        if ($id>0) {
            $this->RegisterMessage($id,VM_UPDATE);
        }
    }

  
    public function Destroy()
    {
        //Never delete this line!
        parent::Destroy();

        //Delete profiles
       $this->DeleteProfileList ('ProfileList');
    }

    private function DeleteProfileList (string $listName) :void
    {
          $list = @$this->ReadAttributeString($listName);
          if (!is_string($list)) return;
          $list = trim($list);
          if  (strlen($list) == 0) return;

          foreach ($this->GetArrayFromString($list) as $item) {
                if (is_string($item)) {
                     $cleanedItem = trim($item);
                     if (strlen($cleanedItem) > 0) $this->DeleteProfile($cleanedItem);
                }
          }
    }

    private function DeleteProfile(string $profileName) : void
    {
        if (empty($profileName)) return;
         $profile =  $this->CreateProfileName($profileName);
         if (@IPS_VariableProfileExists($profile)) {
                IPS_DeleteVariableProfile($profile);
         }
    }

    private function GetArrayFromString (string $itemsString)
    {
        return explode(',', $itemsString);
    }

    private function CreateProfileName (string $profileName) : string
    {
         return self::MODULE_PREFIX . '.' . $this->InstanceID . '.' . $profileName;
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        /*
        $this->SendDebug('MessageSink', 'Message from SenderID ' . $SenderID . ' with Message ' . $Message . "\r\n Data: " . print_r($Data, true), 0);
        if (!empty($Data)) {
            foreach ($Data as $key => $value) {
                $this->SendDebug(__FUNCTION__, 'Data[' . $key . '] = ' . json_encode($value), 0);
            }
        }
        */
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
                $this->SendDebug(__FUNCTION__, 'Der Wochenplan Status hat sich auf ' . $Data[0] . ' geändert.', 0);
               
                //Wochenplan Status
                if ($SenderID == $this->ReadPropertyInteger('WeekTimerStatus')) {   
                    $this->SetWeekTimerStatus($Data[0]);
                }
                else if ($this->ReadPropertyInteger('ExpertModeID') == $SenderID)
                {
                    $this->HandleExpertSwitch($SenderID);
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

   public function WeekTimerAction (int $action) : void
   {
       $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
       $this->SetValue('WeekTimerStatus',$action); 
   }

   private function TriggerAction(): void
   {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        if (!$this->ValidateEventPlan()) $actionID = 0;
        else $actionID = $this->DetermineAction(true);
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
            case self::HeatUndef:
                return self::Automatik;
       }
       return $value;
   }

   private function HandleExpertSwitch(int $id)
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        $status = !GetValueBoolean($id);
        if ($id==0)  $status = false;
        $itemString = $this->ReadAttributeString('ExpertListHide');
        foreach (explode(',', $itemString) as $item)
        {
            $this->HideItem($item,$status);
        }
        $itemString = $this->ReadAttributeString('ExpertListLock');
        foreach (explode(',', $itemString) as $item)
        {
            $this->LockItem($item,$status);
        }
        $this->HideItem('ColorFadeTime',$status || !$this->ReadPropertyBoolean('UseFading'));
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
        if ($id>0) RequestAction($id, $value);
        
   }

    private function ValidateEventPlan(): bool
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        $result = false;
        $weeklySchedule = $this->ReadPropertyInteger('WeekTimer');
        if ($weeklySchedule != 0 && @IPS_ObjectExists($weeklySchedule)) {
            $event = IPS_GetEvent($weeklySchedule);
            if ($event['EventActive'] == 1) {
                $result = true;
            }
        }
        return $result;
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
                //$this->StartAutomaticColor(); //wird ohenhin bei Änderung in MessageSink verarbeitet
                break;
            case "AdaptRoomTemperature":
                 $this->SetValue($Ident, $Value);
                 $this->SendAdaptRoomTemperature($Value);
                break;
        }
    }

    private function DetermineAction(bool $isChecked): int
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        $actionID = 0;
        if ($isChecked || $this->ValidateEventPlan()) {
            $event = IPS_GetEvent($this->ReadPropertyInteger('WeekTimer'));
            if (!$event['EventActive']) return 0;
            $timestamp = time();
            $searchTime = date('H', $timestamp) * 3600 + date('i', $timestamp) * 60 + date('s', $timestamp);
            $weekDay = date('N', $timestamp);
            foreach ($event['ScheduleGroups'] as $group) {
                if (($group['Days'] & pow(2, $weekDay - 1)) > 0) {
                    $points = $group['Points'];
                    foreach ($points as $point) {
                        $startTime = $point['Start']['Hour'] * 3600 + $point['Start']['Minute'] * 60 + $point['Start']['Second'];
                        if ($startTime <= $searchTime) {
                            $actionID = $point['ActionID'];
                        }
                    }
                }
            }
        }
        return $actionID;
    }

    private function HandleOpMode (int $opmode)
    {
        $hide=true;

         switch($opmode) {
            case self::Aus: //Aus 
            case self::Manuell: //Handbetrieb
                 $this->HideItemById ( $this->ReadAttributeInteger('IdRoomThermostat'),true);
                 $this->HideItemById ( $this->GetIDForIdent('WeekTimerStatus'),true);
                 $this->HideItemById ( $this->ReadAttributeInteger('WeekTimer'),true);
                break;
            case self::Automatik: //Automatikbetrieb
                $hide= $this->ReadAttributeInteger('WeekTimer') == 0;
                $this->HideItemById ( $this->ReadAttributeInteger('WeekTimer'),$hide);
                $this->HideItemById ($this->GetIDForIdent('WeekTimerStatus'),$hide);
                $this->HideItemById ($this->ReadAttributeInteger('IdRoomThermostat'),false);
                $this->TriggerAction(); 
                $opmode = $this->GetControlOpMode($this->GetValue('WeekTimerStatus'));
                break;
            default:
         }
         $this->SendOpMode($opmode);
    }

    private function HideItemById (int $id, bool $hide )
    {
        if ($id==0) return;
        IPS_SetHidden($id,$hide);
    }

    private function LockItem(string $item,bool $status)
    {
        $id = $this->GetIDForIdent($item);
        IPS_SetDisabled($id, $status);
    }
     private function HideItem(string $item,bool $status) :void
    {
        if (empty($item)) return;
        $id = $this->GetIDForIdent($item);
        IPS_SetHidden($id, $status);
    }

    #################### Private

    private function KernelReady(): void
    {
        $this->ApplyChanges();
    }
}