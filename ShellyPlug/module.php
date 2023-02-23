<?php

declare(strict_types=1);
class ShellyPlug extends IPSModule
{
    private const MODULE_PREFIX = 'SPL';
    private const MODULE_NAME = 'ShellyPlug';
    private const ProfileList = 'Status';
    private const  Transparent = 0xffffff00;
    private const  Red = 0xFF0000;
    private const  Yellow = 0xFFFF00;
    private const  Green=0x00FF00;
    private const  Blue=0x0000FF;

    public function Create()
    {
        //Never delete this line!
        parent::Create();

        $this->DeleteProfileList (self::ProfileList);

        //Profiles
        $variable = 'Status';
        $profileName = $this->CreateProfileName($variable);

        if (!IPS_VariableProfileExists($profileName)) {
            IPS_CreateVariableProfile($profileName, 1);
            IPS_SetVariableProfileValues($profileName, -1, 1, 0);
            IPS_SetVariableProfileIcon($profileName, "Lamp");
            IPS_SetVariableProfileAssociation($profileName, -1, "Undefiniert", "", self::Red);
            IPS_SetVariableProfileAssociation($profileName, 0, "Aus", "",  self::Transparent);
            IPS_SetVariableProfileAssociation($profileName, 1, "Ein", "", self::Green);
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
    private function RegisterStatusUpdate(string $statusName) : bool
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

    public function TimerSwitch() : void
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

    private function SetSwitchStatus() : void
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

    private function Status (string $jsonCode) : int
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

        $this-> DeleteProfileList (self::ProfileList);
      
    }
    
    private function DeleteProfileList (string $list) :void
    {
          if (!is_string($list)) return;
          $list = trim($list);
          if  (strlen($list) == 0) return;

          foreach ($this->GetArrayFromString($list) as $item) {
                if (is_string($item)) {
                     $cleanedItem = trim($item);
                     if (strlen($cleanedItem) > 0)
                     {
                        $this->DeleteProfile($cleanedItem);
                     }
                }
          }
    }

    private function DeleteProfile(string $profileName) : void
    {
        if (empty($profileName)) return;
         $profile =  $this->CreateProfileName($profileName);
         IPS_LogMessage( $this->InstanceID,'Lösche Profil ' .$profile . '.');
         if (@IPS_VariableProfileExists($profile)) {
                IPS_DeleteVariableProfile($profile);
         }
    }

    private function CreateProfileName (string $profileName) : string
    {
         return self::MODULE_PREFIX . '.' . $this->InstanceID . '.' . $profileName;
    }

    //Wird aufgerufen, wenn in der Form für das Module was geändert wurde und das "Änderungen Übernehmen" bestätigt wird.
    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();

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

        if ($this->RegisterStatusUpdate('SwitchVariable')) {
            if ($this->ReadPropertyInteger('PollingTime') == 0) {
                $this->SetTimerInterval('SPL_Timer', 0);
            } else {
                $this->SetTimerInterval('SPL_Timer', $this->ReadPropertyInteger('PollingTime')*1000);
            }
        }
    }

    public function UpdateTimer() : void
    {
        $this->SetSwitchStatus();
    }

}
