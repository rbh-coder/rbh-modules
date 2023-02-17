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

include_once __DIR__ . '/helper/HKTS_autoload.php';

class HeatingZoneController extends IPSModule
{
    use HKTS_BackupRestore;
    use HKTS_Config;
    use HKTS_DoorWindowSensors;
    use HKTS_WeeklySchedule;

    //Constants
    private const LIBRARY_GUID = '{016A5116-600D-1C0B-9CA8-20F59140AD40}';
    private const MODULE_NAME = 'HeatingZoneController';
    private const MODULE_PREFIX = 'HZCTRL';
    private const MODULE_VERSION = '1.0, 14.02.2023';
    private const MINIMUM_DELAY_MILLISECONDS = 100;
  
    public function Create()
    {
        //Never delete this line!
        parent::Create();

        ########## Properties
         $this->RegisterAttributeString('ProfileList',"AutomaticRelease,OpMode,BoostMode,DoorWindowState");
         foreach ($this->GetArrayFromString($this->ReadAttributeString('ProfileList')) as $item) {
           $this->DeleteProfile($item);

        //Info
        $this->RegisterPropertyString('Note', '');
        //Functions
        $this->RegisterPropertyBoolean('EnableWeeklySchedule', true);
        $this->RegisterPropertyBoolean('EnableTermostat', true);
        $this->RegisterPropertyBoolean('EnableBoostMode', true);
        $this->RegisterPropertyBoolean('EnableRoomTemperature', true);
        $this->RegisterPropertyBoolean('EnableDoorWindowState', true);
        $this->RegisterPropertyBoolean('EnableBoostModeTimer', true);
        $this->RegisterPropertyBoolean('EnableDoorWindowStateTimer', true);
       
        $this->RegisterPropertyInteger('RoomTemperature', 0);
      
        //Temperatures
        $this->RegisterPropertyFloat('SetBackTemperature', 18.0);
        $this->RegisterPropertyFloat('HeatingTemperature', 22.0);
        $this->RegisterPropertyFloat('BoostTemperature', 30.0);
        //Mode duration
        $this->RegisterPropertyInteger('BoostDuration', 5);
        //Weekly schedule
        $this->RegisterPropertyInteger('WeeklySchedule', 0);
        //Door and window sensors
        $this->RegisterPropertyString('DoorWindowSensors', '[]');
        $this->RegisterPropertyInteger('ReviewDelay', 0);
        $this->RegisterPropertyBoolean('ReduceTemperature', true);
        $this->RegisterPropertyFloat('OpenDoorWindowTemperature', 12);
        $this->RegisterPropertyBoolean('ReactivateBoostMode', false);
   
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
        $this->RegisterVariableInteger($variable, $this->Translate('Operation Mode'),$profileName, 0);
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
        $this->RegisterVariableBoolean($variable, $this->Translate('Automatic Release'), $profileName, 60);
        $this->EnableAction($variable);
        //Variable für Änderungen registrieren
        $this->RegisterMessage($this->GetIDForIdent($variable),VM_UPDATE);


        //Set point temperature
        $variable = 'SetTemperature';
        $profileName =  $this->CreateProfileName($variable);
        if (!IPS_VariableProfileExists($profileName)) {
            IPS_CreateVariableProfile($profileName, 2);
        
        IPS_SetVariableProfileIcon($profileName, 'Temperature');
        IPS_SetVariableProfileValues($profileName, 0, 31, 0.5);
        IPS_SetVariableProfileDigits($profileName, 1);
        IPS_SetVariableProfileText($profileName, '', ' °C');
        }
        $this->RegisterVariableFloat( $variable, 'Soll-Temperatur', $profileName, 30);
        $this->EnableAction( $variable);
        

        //Boost mode
        $variable = 'BoostMode';
        $profileName =  $this->CreateProfileName($variable);
        $profile = self::MODULE_PREFIX . '.' . $this->InstanceID . '.BoostMode';
        if (!IPS_VariableProfileExists($profile)) {
            IPS_CreateVariableProfile($profile, 0);
            IPS_SetVariableProfileAssociation($profileName), 0, 'Aus', 'Flame', 0x0000FF);
            IPS_SetVariableProfileAssociation($profileName, 1, 'An', 'Flame', 0xFF0000);
         }
        $this->RegisterVariableBoolean($variable, 'Boost-Modus', $profileName, 40);
        $this->EnableAction($variable);
        
        //Room temperature
        $variable = 'RoomTemperature';
        $this->RegisterVariableFloat( $variable, 'Raum-Temperatur', 'Temperature', 70);

        //Door and window state
        $variable = 'DoorWindowState';
        $profileName =  $this->CreateProfileName($variable);
        if (!IPS_VariableProfileExists($profileName)) {
            IPS_CreateVariableProfile($profileName, 0);
            IPS_SetVariableProfileAssociation($profileName, 0, 'Geschlossen', 'Window', 0x00FF00);
            IPS_SetVariableProfileAssociation($profileName, 1, 'Geöffnet', 'Window', 0x0000FF);
         }
        $this->RegisterVariableBoolean($variable, 'Tür- / Fensterstatus', $profileName, 80);
       
        //Boost mode timer info
        $id = @$this->GetIDForIdent('BoostModeTimer');
        $this->RegisterVariableString('BoostModeTimer', 'Boost-Modus Timer', '', 110);
        if (!$id) {
            IPS_SetIcon($this->GetIDForIdent('BoostModeTimer'), 'Clock');
        }

        //Door window state timer info
        $id = @$this->GetIDForIdent('DoorWindowStateTimer');
        $this->RegisterVariableString('DoorWindowStateTimer', 'Tür- / Fensterstatus Timer', '', 130);
        if (!$id) {
            IPS_SetIcon($this->GetIDForIdent('DoorWindowStateTimer'), 'Clock');
        }

        ########## Timer

        $this->RegisterTimer('ReviewDoorWindowSensors', 0, self::MODULE_PREFIX . '_ReviewDoorWindowSensors(' . $this->InstanceID . ');');
        $this->RegisterTimer('DeactivateBoostMode', 0, self::MODULE_PREFIX . '_ToggleBoostMode(' . $this->InstanceID . ', false);');
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
        

        ########## WebFront options


        //Weekly schedule
        $id = @IPS_GetLinkIDByName('Wochenplan', $this->InstanceID);
        if (is_int($id)) {
            $hide = true;
            if ($this->ValidateEventPlan()) {
                if ($this->ReadPropertyBoolean('EnableWeeklySchedule') && $this->GetValue('AutomaticMode')) {
                    $hide = false;
                }
            }
            IPS_SetHidden($id, $hide);
        }

        //Set point temperature
        IPS_SetHidden($this->GetIDForIdent('SetTemperature'), !$this->ReadPropertyBoolean('EnableSetPointTemperature'));

        //Boost mode
        IPS_SetHidden($this->GetIDForIdent('BoostMode'), !$this->ReadPropertyBoolean('EnableBoostMode'));

        //Room temperature
        IPS_SetHidden($this->GetIDForIdent('RoomTemperature'), !$this->ReadPropertyBoolean('EnableRoomTemperature'));

        //Door and window state
        IPS_SetHidden($this->GetIDForIdent('DoorWindowState'), !$this->ReadPropertyBoolean('EnableDoorWindowState'));

        //Boost mode timer info
        IPS_SetHidden($this->GetIDForIdent('BoostModeTimer'), !$this->ReadPropertyBoolean('EnableBoostModeTimer'));

        //Door windows state timer info
        IPS_SetHidden($this->GetIDForIdent('DoorWindowStateTimer'), !$this->ReadPropertyBoolean('EnableDoorWindowStateTimer'));

        ########## References and Messages

        //Delete all references
        foreach ($this->GetReferenceList() as $referenceID) {
            $this->UnregisterReference($referenceID);
        }

        //Delete all message registrations
        foreach ($this->GetMessageList() as $senderID => $messages) {
            foreach ($messages as $message) {
                if ($message == EM_UPDATE) {
                    $this->UnregisterMessage($id, EM_UPDATE);
                }
                if ($message == VM_UPDATE) {
                    $this->UnregisterMessage($senderID, VM_UPDATE);
                }
            }
        }

        //Register references and messages
        if (!$this->CheckMaintenanceMode()) {
            $this->SendDebug(__FUNCTION__, 'Referenzen und Nachrichten werden registriert.', 0);
            //Weekly schedule
            $id = $this->ReadPropertyInteger('WeeklySchedule');
            if ($id != 0 && @IPS_ObjectExists($id)) {
                $this->RegisterReference($id);
                $this->RegisterMessage($id, EM_UPDATE);
            }
            //Room temperature
            $id = $this->ReadPropertyInteger('RoomTemperature');
            if ($id != 0 && IPS_ObjectExists($id)) {
                $this->RegisterReference($id);
                $this->RegisterMessage($id, VM_UPDATE);
            }
            //Door and window sensors
            foreach (json_decode($this->ReadPropertyString('DoorWindowSensors')) as $sensor) {
                if ($sensor->UseSensor) {
                    $id = $sensor->ID;
                    if ($id != 0 && @IPS_ObjectExists($id)) {
                        $this->RegisterReference($id);
                        $this->RegisterMessage($id, VM_UPDATE);
                    }
                }
            }
         
        }

        ########## Links

        //Weekly schedule
        $targetID = $this->ReadPropertyInteger('WeeklySchedule');
        $linkID = @IPS_GetLinkIDByName('Schaltuhr', $this->InstanceID);
        if ($targetID != 0 && @IPS_ObjectExists($targetID)) {
            //Check for existing link
            if (!is_int($linkID) && !$linkID) {
                $linkID = IPS_CreateLink();
            }
            IPS_SetParent($linkID, $this->InstanceID);
            IPS_SetPosition($linkID, 20);
            IPS_SetName($linkID, 'Schaltuhr');
            IPS_SetIcon($linkID, 'Calendar');
            IPS_SetLinkTargetID($linkID, $targetID);
        } else {
            if (is_int($linkID)) {
                IPS_SetHidden($linkID, true);
            }
        }

        ########## Timer

        $this->SetTimerInterval('DeactivateBoostMode', 0);
        $this->SetValue('BoostModeTimer', '-');
        $this->SetTimerInterval('ReviewDoorWindowSensors', 0);
        $this->SetValue('DoorWindowStateTimer', '-');

        ########## Misc
    }

    public function Destroy()
    {
        //Never delete this line!
        parent::Destroy();

        //Delete profiles
        foreach ($this->GetArrayFromString($this->ReadAttributeString('ProfileList')) as $item) {
           $this->DeleteProfile($item);
    }

    private function DeleteProfile(string $profileName)
    {
        if (empty($profileName)) return;
         $profile =  $this->CreateProfileName($profileName);
         if (@IPS_VariableProfileExists($profile)) {
                IPS_DeleteVariableProfile($profile);
            }
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
                break;

            case EM_UPDATE:

                //$Data[0] = last run
                //$Data[1] = next run

                if ($this->CheckMaintenanceMode()) {
                    return;
                }

                //Weekly schedule
                //$this->TriggerAction(true);
                $scriptText = self::MODULE_PREFIX . '_TriggerAction(' . $this->InstanceID . ', true);';
                IPS_RunScriptText($scriptText);
                break;

        }
    }

     public function UpdateRoomTemperature(): void
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        if ($this->CheckMaintenanceMode()) {
            return;
        }
        $id = $this->ReadPropertyInteger('RoomTemperature');
        if ($id == 0 || !@IPS_ObjectExists($id)) {
            return;
        }
       // $this->SetValue('RoomTemperature', GetValue($this->ReadPropertyInteger('RoomTemperature')));
    }



    #################### Request action

    public function RequestAction($Ident, $Value)
    {
        switch ($Ident) {
            case 'AutomaticMode':
                $this->ToggleAutomaticMode($Value);
                break;

            case 'SetTemperature':
                $this->ToggleSetPointTemperature($Value);
                break;

            case 'BoostMode':
                $this->ToggleBoostMode($Value);
                break;
        }
    }

    public function ToggleAutomaticMode(bool $State): void
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        $this->SetValue('AutomaticMode', $State);
        //Weekly schedule visibility
        $id = @IPS_GetLinkIDByName('Wochenplan', $this->InstanceID);
        if (is_int($id)) {
            $hide = true;
            if ($this->ReadPropertyBoolean('EnableWeeklySchedule') && $State) {
                $hide = false;
            }
            IPS_SetHidden($id, $hide);
        }
    }

    #################### Private

    private function KernelReady(): void
    {
        $this->ApplyChanges();
    }
}