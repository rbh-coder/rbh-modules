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
         $this->RegisterAttributeString('ProfileList',"AutomaticRelease,OpMode,AdaptRoomTemperature");
         $this->RegisterAttributeString('LinkList', "WeekTimer,IdRoomThermostat,IdRoomTemperature,IdHeatingPump,IdMixerPosition,IdSetHeat,IdActHeat");

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

         //AutomaticRelease
        $variable = 'AutomaticRelease';
        $profileName = $this->CreateProfileName($variable);
        if (!IPS_VariableProfileExists($profileName)) {
            IPS_CreateVariableProfile($profileName, 0);
            IPS_SetVariableProfileIcon($profileName, "Ok");
            IPS_SetVariableProfileAssociation($profileName, false, "Aus", "", self::Blue);
            IPS_SetVariableProfileAssociation($profileName, true, "Ein", "", self::Green);
        }
        $this->RegisterVariableBoolean($variable, $this->Translate('Status Week Timer'), $profileName, 30);
        $this->EnableAction($variable);
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


        //Weekly schedule
        /*
        $id = @IPS_GetLinkIDByName('Wochenplan', $this->InstanceID);
        if (is_int($id)) {
            $hide = true;
            if ($this->ValidateEventPlan()) {
                if ($this->GetValue('OpMode') == self::Automatik) {
                    $hide = false;
                }
            }
            IPS_SetHidden($id, $hide);
        }
        */
       
        ########## References and Messages


        //Register references and messages
        
        $this->SendDebug(__FUNCTION__, 'Referenzen und Nachrichten werden registriert.', 0);
        
        //Weekly schedule
        $id = $this->ReadPropertyInteger('WeekTimer');
        if ($id != 0 && @IPS_ObjectExists($id)) {
            $this->RegisterReference($id);
            $this->RegisterMessage($id, EM_UPDATE);
        }
        

        ########## Links

        //Weekly schedule
        $this->WriteAttributeInteger('WeekTimer',$this->CreateLink ( $this->ReadPropertyInteger('WeekTimer'),'Wochenplan','Calendar', 20));
        $this->WriteAttributeInteger('IdRoomThermostat',$this->CreateLink ( $this->ReadPropertyInteger('IdRoomThermostat'),'Heizungsanforderung','Flame', 100));
        $this->WriteAttributeInteger('IdRoomTemperature',$this->CreateLink ( $this->ReadPropertyInteger('IdRoomTemperature'),'Raumtemperatur','Temperature', 110));
        $this->WriteAttributeInteger('IdHeatingPump',$this->CreateLink ( $this->ReadPropertyInteger('IdHeatingPump'),'Heizungspumpe','TurnRight', 120));
        $this->WriteAttributeInteger('IdMixerPosition',$this->CreateLink ( $this->ReadPropertyInteger('IdMixerPosition'),'Mischerposition','Intensity', 140));
        $this->WriteAttributeInteger('IdSetHeat',$this->CreateLink ( $this->ReadPropertyInteger('IdSetHeat'),'Vorlauftemperatur Sollwert','Temperature',160));
        $this->WriteAttributeInteger('IdActHeat',$this->CreateLink ( $this->ReadPropertyInteger('IdActHeat'),'Vorlauftemperatur Istwert','Temperature',180));

        ########## Timer

      

        ########## Misc
  
        $profileName =  $this->CreateProfileName('OpMode');
        if (IPS_VariableProfileExists($profileName)) {
            if (($this->ReadAttributeInteger('WeekTimer') == 0) && ($this->ReadAttributeInteger('IdRoomThermostat')==0))
            {
                IPS_SetVariableProfileValues($profileName, 0, 1, 0);
                IPS_SetVariableProfileAssociation($profileName, 0, "Aus", "", self::Transparent);
                IPS_SetVariableProfileAssociation($profileName, 1, "Hand", "", self::Yellow);
            }
            else {
                IPS_SetVariableProfileValues($profileName, 0, 2, 0);
                IPS_SetVariableProfileAssociation($profileName, 0, "Aus", "", self::Transparent);
                IPS_SetVariableProfileAssociation($profileName, 1, "Hand", "", self::Yellow);
                IPS_SetVariableProfileAssociation($profileName, 2, "Automatik", "", self::Green);
            }

        }
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

    private function HideItem(string $item,bool $status) :void
    {
        if (empty($item)) return;
        $id = $this->GetIDForIdent($item);
        IPS_SetHidden($id, $status);
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
          foreach ($this->GetArrayFromString($this->ReadAttributeString( $listName)) as $item) {
                if (is_string($item)) {
                     $cleanedItem = trim($item);
                     if ($cleanedItem != "") $this->DeleteProfile($cleanedItem);
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
        $this->SendDebug('MessageSink', 'Message from SenderID ' . $SenderID . ' with Message ' . $Message . "\r\n Data: " . print_r($Data, true), 0);
        if (!empty($Data)) {
            foreach ($Data as $key => $value) {
                $this->SendDebug(__FUNCTION__, 'Data[' . $key . '] = ' . json_encode($value), 0);
            }
        }
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

                /*
                //Room temperature
                if ($SenderID == $this->ReadPropertyInteger('RoomTemperature')) {
                    if ($Data[1]) {
                        $this->SendDebug(__FUNCTION__, 'Die Raum-Temperatur hat sich auf ' . $Data[0] . '°C geändert.', 0);
                        //$this->UpdateRoomTemperature();
                        $scriptText = self::MODULE_PREFIX . '_UpdateRoomTemperature(' . $this->InstanceID . ');';
                        IPS_RunScriptText($scriptText);
                    }
                }
                //Door and window sensors
                $doorWindowSensors = json_decode($this->ReadPropertyString('DoorWindowSensors'), true);
                if (!empty($doorWindowSensors)) {
                    if (in_array($SenderID, array_column($doorWindowSensors, 'ID'))) {
                        if ($Data[1]) {
                            //$this->CheckDoorWindowSensors();
                            $scriptText = self::MODULE_PREFIX . '_CheckDoorWindowSensors(' . $this->InstanceID . ');';
                            IPS_RunScriptText($scriptText);
                        }
                    }
                }
                */
                break;

            case EM_UPDATE:

                //$Data[0] = last run
                //$Data[1] = next run
                //Weekly schedule

                $this->TriggerAction();
                //$scriptText = self::MODULE_PREFIX . '_TriggerAction(' . $this->InstanceID . ', true);';
                //IPS_RunScriptText($scriptText);
                break;

        }
    }

   public function TriggerAction(): void
   {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        if (!$this->ValidateEventPlan()) {
            return;
        }
        //Trigger action only in automatic mode
        if ($this->GetValue('OpMode') == self::Automatik) {
            $actionID = $this->DetermineAction();
            switch ($actionID) {
                case self::HeatUndef: # No actual action found
                    $this->SendDebug(__FUNCTION__, '0 = Keine Aktion gefunden!', 0);
                    $this->SetValue('AutomaticRelease',false); 
                    break;

                case self::HeatOff: # Heizung AUS
                    $this->SendDebug(__FUNCTION__, '1 = AUS', 0);
                    $this->SetWeekTimerAction ($actionID);
                    $this->SetValue('AutomaticRelease',false); 
                    break;

                case self::HeatOn: # Heizung EIN
                    $this->SendDebug(__FUNCTION__, '2 = EIN', 0);
                    $this->SetWeekTimerAction ($actionID);
                    $this->SetValue('AutomaticRelease',true); 
                    break;
            }
        }
   }

   private function SetWeekTimerAction (int $action)
   {

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
            case "AutomaticRelease":
                 $this->SetValue($Ident, $Value);
                //$this->StartAutomaticColor(); //wird ohenhin bei Änderung in MessageSink verarbeitet
                break;
            case "AdaptRoomTemperature":
                 $this->SetValue($Ident, $Value);
                //$this->StartAutomaticColor(); //wird ohenhin bei Änderung in MessageSink verarbeitet
                break;
        }
    }

    private function DetermineAction(): int
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        $actionID = 0;
        if ($this->ValidateEventPlan()) {
            $event = IPS_GetEvent($this->ReadPropertyInteger('WeekTimer'));
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
                break;
            case self::Manuell: //Handbetrieb
                break;
            case self::Automatik: //Automatikbetrieb
                $hide=false;
                break;
            default:
        }

         $this->HideItemById ( $this->ReadAttributeInteger('WeekTimer'),$hide );
         //Wenn Weektimer gar nicht referenziert ist, dann den Status auch nicht anzeigen 
         if  ($this->ReadAttributeInteger('WeekTimer') == 0)
         {
             $this->HideItemById ( $this->GetIDForIdent('AutomaticRelease'),true);
         }
         else 
         {
	        $this->HideItemById ( $this->GetIDForIdent('AutomaticRelease'),$hide );
         }

         $this->HideItemById ( $this->ReadAttributeInteger('IdRoomThermostat'),$hide );
    }

    private function HideItemById (int $id, bool $hide )
    {
        if ($id==0) return;
        IPS_SetHidden($id,$hide);
    }

    #################### Private

    private function KernelReady(): void
    {
        $this->ApplyChanges();
    }
}