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
  
    public function Create()
    {
        //Never delete this line!
        parent::Create();

         //Some color definitions
        $transparent = 0xffffff00;
        $red=0xFF0000;
        $yellow = 0xFFFF00;
        $green=0x00FF00;
        $blue=0x0000FF;

        ########## Properties
         $this->RegisterAttributeString('ProfileList',"AutomaticRelease,OpMode,CorrectRoomTemperature");
         $this->RegisterAttributeString('LinkList', "IdRoomThermostat,IdRoomTemperature,IdHeatingPump,IdMixerPosition,IdSetHeat,IdActHeat");

        foreach ($this->GetArrayFromString($this->ReadAttributeString('ProfileList')) as $item) {
           $this->DeleteProfile($item);
        }

        //Info
        $this->RegisterPropertyString('Note', '');
        //Functions
     
        $this->RegisterPropertyInteger('RoomTemperature', 0);
      
        //Temperatures
        $this->RegisterPropertyFloat('SetBackTemperature', 18.0);
        $this->RegisterPropertyFloat('HeatingTemperature', 22.0);
        $this->RegisterPropertyFloat('BoostTemperature', 30.0);
      
        //Weekly schedule
        $this->RegisterPropertyInteger('WeekTimer', 0);
       
   
        ########## Variables

       //Variablen --------------------------------------------------------------------------------------------------------
       
       //OpMode
        $variable = 'OpMode';
        $profileName =  $this->CreateProfileName($variable);
        if (!IPS_VariableProfileExists($profileName)) {
            IPS_CreateVariableProfile($profileName, 1);
            IPS_SetVariableProfileValues($profileName, 0, 2, 0);
            IPS_SetVariableProfileIcon($profileName, "Shutter");
            IPS_SetVariableProfileAssociation($profileName, 0, "Aus", "", $transparent);
            IPS_SetVariableProfileAssociation($profileName, 1, "Hand", "", $yellow);
            IPS_SetVariableProfileAssociation($profileName, 2, "Automatik", "", $green);
        }
        $this->RegisterVariableInteger($variable, $this->Translate('Operation Mode'),$profileName, 10);
        $this->EnableAction($variable);

         //AutomaticRelease
        $variable = 'AutomaticRelease';
        $profileName = $this->CreateProfileName($variable);
        if (!IPS_VariableProfileExists($profileName)) {
            IPS_CreateVariableProfile($profileName, 0);
            IPS_SetVariableProfileIcon($profileName, "Ok");
            IPS_SetVariableProfileAssociation($profileName, false, "Aus", "", $transparent);
            IPS_SetVariableProfileAssociation($profileName, true, "Ein", "", $green);
        }
        $this->RegisterVariableBoolean($variable, $this->Translate('Automatic Release'), $profileName, 30);
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
        }
        $this->RegisterVariableFloat($variable, $this->Translate('Adapt Room Temperature'), $profileName, 60);
        $this->EnableAction($variable);

        $this->RegisterVariableIds($this->ReadAttributeString('LinkList'));
      
        ########## Timer
    }

    private function RegisterVariableIds(string $itemsString)
    {
        foreach (explode(',', $itemsString) as $item) {
            if ($item != "") $this->RegisterPropertyInteger($item, 0);
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
                    $this->UnregisterMessage($senderId, EM_UPDATE);
                }
                if ($message == VM_UPDATE) {
                    $this->UnregisterMessage($senderID, VM_UPDATE);
                }
            }
        }

        ########## WebFront options


        //Weekly schedule
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
        CreateLink ( $this->ReadPropertyInteger('WeekTimer'),'Wochenplan','Calendar', 20);
        CreateLink ( $this->ReadPropertyInteger('IdRoomThermostat'),'Raumptemperatur anpassen','Temperature', 100);
        CreateLink ( $this->ReadPropertyInteger('IdRoomTemperature'),'Raumptemperatur','Temperature', 110);
        CreateLink ( $this->ReadPropertyInteger('IdHeatingPump'),'Heizungspumpe','TurnRight', 120);
        CreateLink ( $this->ReadPropertyInteger('IdMixerPosition'),'Mischerposition','Intensity', 140);
        CreateLink ( $this->ReadPropertyInteger('IdSetHeat'),'Vorlauftemperatur Sollwert','Temperature',160);
        CreateLink ( $this->ReadPropertyInteger('IdActHeat'),'Vorlauftemperatur Istwert','Temperature',180);

        ########## Timer

      

        ########## Misc
    }

    private function CreateLink (int $targetID,string $name,string $iconName, int $position)
    {
        $linkID = @IPS_GetLinkIDByName($name, $this->InstanceID);
        if ($targetID != 0 && @IPS_ObjectExists($targetID)) {
            //Check for existing link
            if (!is_int($linkID) && !$linkID) {
                $linkID = IPS_CreateLink();
            }
            IPS_SetParent($linkID, $this->InstanceID);
            IPS_SetPosition($linkID,$position);
            IPS_SetName($linkID, $name);
            IPS_SetIcon($linkID,$iconName);
            IPS_SetLinkTargetID($linkID, $targetID);
        } else {
            if (is_int($linkID)) {
                IPS_SetHidden($linkID, true);
            }
        }
    }

    public function Destroy()
    {
        //Never delete this line!
        parent::Destroy();

        //Delete profiles
        foreach ($this->GetArrayFromString($this->ReadAttributeString('ProfileList')) as $item) {
           $this->DeleteProfile($item);
        }
    }

    private function DeleteProfile(string $profileName)
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

    private function CreateProfileName (string $profileName)
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

                //$this->TriggerAction(true);
                $scriptText = self::MODULE_PREFIX . '_TriggerAction(' . $this->InstanceID . ', true);';
                IPS_RunScriptText($scriptText);
                break;

        }
    }

   public function TriggerAction(bool $SetTemperature): void
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        if (!$this->ValidateEventPlan()) {
            return;
        }
        //Trigger action only in automatic mode
        if ($this->GetValue('AutomaticMode')) {
            $actionID = $this->DetermineAction();
            switch ($actionID) {
                case 0: # No actual action found
                    $this->SendDebug(__FUNCTION__, '0 = Keine Aktion gefunden!', 0);
                    break;

                case 1: # Set-back temperature
                    $this->SendDebug(__FUNCTION__, '1 = Absenkmodus', 0);
                    $temperature = $this->ReadPropertyFloat('SetBackTemperature');
                    break;

                case 3: # Heating temperature
                    $this->SendDebug(__FUNCTION__, '2 = Heizmodus', 0);
                    $temperature = $this->ReadPropertyFloat('HeatingTemperature');
                    break;

                case 4: # Boost temperature
                    $this->SendDebug(__FUNCTION__, '3= Boostmodus', 0);
                    $temperature = $this->ReadPropertyFloat('BoostTemperature');
                    break;

            }
           //Irgendwas....
        }
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
                //$this->HandleOpMode($Value);
                break;
            case "AutomaticRelease":
                 $this->SetValue($Ident, $Value);
                //$this->StartAutomaticColor(); //wird ohenhin bei Änderung in MessageSink verarbeitet
                break;
        }
    }

    #################### Private

    private function KernelReady(): void
    {
        $this->ApplyChanges();
    }
}