<?php

declare(strict_types=1);
class ShellyPlug extends IPSModule
{
    private const MODULE_PREFIX = 'SPL';
    private const MODULE_NAME = 'ShellyPlug';

    public function Create()
    {
        //Never delete this line!
        parent::Create();

        //Some color definitions
        $transparent = 0xffffff00;
        $white = 0xffffff;
        $red=0xFF0000;
        $yellow = 0xFFFF00;
        $green_blue=0x0CBAA6;
        $green=0x00FF00;
        $blue=0x0000FF;

       $this->RegisterAttributeString('ProfileList',"Status");

        //Profiles
        $variable = 'Status';
        $profileName = $this->CreateProfileName($variable);
        if (IPS_VariableProfileExists($profileName)) IPS_DeleteVariableProfile($profileName);
        if (!IPS_VariableProfileExists($profileName)) {
            IPS_CreateVariableProfile($profileName, 1);
            IPS_SetVariableProfileValues($profileName, -1, 1, 0);
            IPS_SetVariableProfileIcon($profileName, "Lamp");
            IPS_SetVariableProfileAssociation($profileName, -1, "Undefiniert", "", $red);
            IPS_SetVariableProfileAssociation($profileName, 0, "Aus", "",  $transparent);
            IPS_SetVariableProfileAssociation($profileName, 1, "Ein", "", $green);
        }
        $this->RegisterVariableInteger($variable, $this->Translate('Status'),$profileName, -1);

        $this->RegisterPropertyString('IpAddress', "");
        $this->RegisterPropertyBoolean('Debug', false);
        $this->RegisterPropertyInteger('SwitchVariable', 0);
        $this->RegisterPropertyInteger('PollingTime', 60);

        $this->RegisterTimer('SPL_Timer', 0, 'SPL_UpdateTimer($_IPS[\'TARGET\']);');
        $this->RegisterTimer('SPL_TimerSwitch', 0, 'SPL_TimerSwitch($_IPS[\'TARGET\']);');
    }

    //Wird aufgerufen bei Änderungen in der GUI, wenn für Variable mit
    //void EnableAction (string $Ident)
    //regstriert wird
    public function RequestAction($Ident, $Value)
    {
        switch($Ident) {
            default:
                break;
        }
    }

    //Methode Registriert Variable für die MessageSink, soferne dieser in der Modul-Form aktiviert ist
    public function RegisterStatusUpdate(string $statusName)
    {
        $id= $this->ReadPropertyInteger($statusName);
        //Register for change notification if a variable is defined
        //IPS_LogMessage("ApplyChanges", 'id:'.$id.' name:'.$statusName);
        if ($id>0) {
            $this->RegisterMessage($id, VM_UPDATE);
            return true;
        }
        return false;
    }



    //Wird aufgrufen wenn Variable mit
    //void RegisterMessage (integer $SenderID, integer $NachrichtID)
    //registriert wird
    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        //$Data:
        //0: Aktueller Wert
        //1: Ob es eine Differenz zum alten Wert gibt.
        //2: Alter Wert

        //Hier ist "OnChange" ausprogrammiert, d.h. wenn es keine Differenz zm alten Wert gibt, dann Abflug
        if ($Data[1]==0) {
            return;
        }

        if ($SenderID == $this->ReadPropertyInteger('SwitchVariable')) {
            //Hier eine Timer Task starten, um Code nicht direkt auszuführen
            $this->SetTimerInterval('SPL_TimerSwitch',1);
        }
    }

    public function TimerSwitch()
    {
        $this->SetTimerInterval('SPL_TimerSwitch', 0);

        $status = GetValueBoolean($this->ReadPropertyInteger('SwitchVariable'));
        $ipAddress = $this->ReadPropertyString('IpAddress');

        if (!Sys_Ping ($ipAddress,3000))
        {
            IPS_LogMessage("ShellyPlug", "Cannot reach ".$ipAddress);
            $this->SetValue('Status',-1);
            return;
        }

        $command = $status ? "on" : "off";
        $url="http://".$ipAddress."/relay/0?turn=".$command;
        $actStatus = $this->Status (file_get_contents($url));
        $this->SetValue('Status', $actStatus);

        if ($this->ReadPropertyBoolean('Debug')) {

            $statusValue =  $status ? "ON" : "OFF";
            IPS_LogMessage("ShellyPlug", "Set plug ".$ipAddress. " to ".$statusValue);

            $statusValue = "UNDEFINED";
            switch ($actStatus)
            {
                case 0:
                     $statusValue = "OFF";
                    break;
                case 1:
                     $statusValue = "ON";
                    break;
            }
            IPS_LogMessage("ShellyPlug", "Received status of plug ".$ipAddress. " is ".$statusValue);
        }
    }

    public function GetSwitchStatus()
    {
        $ipAddress = $this->ReadPropertyString('IpAddress');

        if (!Sys_Ping ($ipAddress,3000))
        {
            IPS_LogMessage("ShellyPlug", "Cannot reach ".$ipAddress);
            $this->SetValue('Status',-1);
            return;
        }

        $url="http://".$ipAddress."/relay/0";

        $actStatus = $this->Status (file_get_contents($url));
        $this->SetValue('Status', $actStatus);

        if ($this->ReadPropertyBoolean('Debug')) {
            $statusValue = "UNDEFINED";
            switch ($actStatus)
            {
                case 0:
                     $statusValue = "OFF";
                    break;
                case 1:
                     $statusValue = "ON";
                    break;
            }
            IPS_LogMessage("ShellyPlug", "Received status of plug ".$ipAddress. " is ".$statusValue);
        }
    }

    public function Status (string $jsonCode)
    {
        $status = -1;
        if ( $jsonCode == false) return $status;

        $dataObject = json_decode($jsonCode);
        if (!is_null($dataObject))
        {
            if (!is_null($dataObject->ison)) {
                if ($dataObject->ison == 'true') {
                    $status = 1;
                }
                else
                {
                    $status = 0;
                }
            }
        }
        return  $status;
    }


    public function Destroy()
    {
        //Never delete this line!
        parent::Destroy();

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

    public function CreateProfileName (string $profileName)
    {
         return self::MODULE_PREFIX . '.' . $this->InstanceID . '.' . $profileName;
    }

    //Wird aufgerufen, wenn in der Form für das Module was geändert wurde und das "Änderungen Übernehmen" bestätigt wird.
    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();

        if ($this->RegisterStatusUpdate('SwitchVariable')) {
            if ($this->ReadPropertyInteger('PollingTime') == 0) {
                $this->SetTimerInterval('SPL_Timer', 0);
            } else {
                $this->SetTimerInterval('SPL_Timer', $this->ReadPropertyInteger('PollingTime')*1000);
            }
        }
    }

    public function UpdateTimer()
    {
        $this->GetSwitchStatus();
    }

}
