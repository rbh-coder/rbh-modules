<?php

declare(strict_types=1);
class ShellyPlug extends IPSModule
{
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

        //Profiles
    
        $profileName = "SPL_Status";
        IPS_DeleteVariableProfile($profileName);
        if (!IPS_VariableProfileExists($profileName)) {
            IPS_CreateVariableProfile($profileName, 1);
            IPS_SetVariableProfileValues($profileName, -1, 1, 0);
            IPS_SetVariableProfileIcon($profileName, "Lamp");
            IPS_SetVariableProfileAssociation($profileName, -1, "Undefiniert", "", $red);
            IPS_SetVariableProfileAssociation($profileName, 0, "Aus", "",  $transparent);
            IPS_SetVariableProfileAssociation($profileName, 1, "Ein", "", $green);
        }

        $this->RegisterPropertyString('IpAddress', "");
        $this->RegisterPropertyBoolean('Debug', false);
        $this->RegisterPropertyInteger('SwitchVariable', 0);
        $this->RegisterVariableInteger('Status', $this->Translate('Status'), 'SPL_Status', -1);
        $this->RegisterPropertyInteger('PollingTime', 60);
        $this->RegisterTimer('SPL_Timer', 0, 'SPL_UpdateTimer($_IPS[\'TARGET\']);');
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
        }
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
            $this->SetSwitch($SenderID);
        }
    }


    public function SetSwitch(int $id)
    {
        $status = GetValueBoolean($id);
        $ipAddress = $this->ReadPropertyString('IpAddress');
        $command = $status ? "on" : "off";
        $url="http://".$ipAddress."/relay/0?turn=".$command;
        
        $actStatus = $this->Status (file_get_contents($url));
        $this->SetValue('Status', $actStatus);

        if ($this->ReadPropertyBoolean('DebugMode')) {
            $statusValue =  $status ? "ON" : "OFF";
            IPS_LogMessage("ShellyPlug", "Set plug ".$idAddress. "to ".$statusValue);
            $statusValue =  $actStatus ? "ON" : "OFF";
            IPS_LogMessage("ShellyPlug", "Received status of plug ".$idAddress. "is ".$statusValue);
        }
    }

    public function GetSwitchStatus()
    {
        $ipAddress = $this->ReadPropertyString('IpAddress');
        $url="http://".$ipAddress."/relay/0";
      
        $actStatus = $this->Status (file_get_contents($url));
        $this->SetValue('Status', $actStatus);

        if ($this->ReadPropertyBoolean('DebugMode')) {
            $statusValue =  $actStatus ? "ON" : "OFF";
            IPS_LogMessage("ShellyPlug", "Received status of plug ".$idAddress. "is ".$statusValue);
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
                    $actStatus = 1;
                }
                else 
                {
                    $actStatus = 0;
                }
            }
        }
    }
   

    public function Destroy()
    {
        //Never delete this line!
        parent::Destroy();
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
