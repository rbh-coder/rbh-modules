<?php

declare(strict_types=1);
class PulseActor extends IPSModule
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
        $this->RegisterAttributeString('SwitchList', "SwitchActorID");
        $this->RegisterAttributeString('StatusList', "StatusActorID");
        $this->RegisterAttributeString('ExpertListHide',"");
        $this->RegisterAttributeString('ExpertListLock',"OpMode,PulseTime,PauseTime");

        //Profiles
        $profileName = "PAC_Switch";
        IPS_DeleteVariableProfile($profileName);
        if (!IPS_VariableProfileExists($profileName)) {
            IPS_CreateVariableProfile($profileName, 0);
            IPS_SetVariableProfileIcon($profileName, "Power");
            IPS_SetVariableProfileAssociation($profileName, false, "Aus", "", $transparent);
            IPS_SetVariableProfileAssociation($profileName, true, "Ein", "", $green);
        }

        $profileName = "PAC_OpMode";
        IPS_DeleteVariableProfile($profileName);
        if (!IPS_VariableProfileExists($profileName)) {
            IPS_CreateVariableProfile($profileName, 1);
            IPS_SetVariableProfileValues($profileName, 0, 2, 0);
            IPS_SetVariableProfileIcon($profileName, "Shutter");
            IPS_SetVariableProfileAssociation($profileName, 0, "Aus", "", $transparent);
            IPS_SetVariableProfileAssociation($profileName, 1, "Hand", "", $yellow);
            IPS_SetVariableProfileAssociation($profileName, 2, "Automatik", "", $green);
        }

        $profileName = "PAC_PulseTime";
        IPS_DeleteVariableProfile($profileName);
        if (!IPS_VariableProfileExists($profileName)) {
            IPS_CreateVariableProfile($profileName, 1);
            IPS_SetVariableProfileText($profileName, "", " sec");
            IPS_SetVariableProfileValues($profileName, 1, 60, 1);
            IPS_SetVariableProfileIcon($profileName, "Clock");
        }

        $profileName = "PAC_PauseTime";
        IPS_DeleteVariableProfile($profileName);
        if (!IPS_VariableProfileExists($profileName)) {
            IPS_CreateVariableProfile($profileName,0,2,0);
            IPS_SetVariableProfileText($profileName, "", " sec");
            IPS_SetVariableProfileValues($profileName, 0, 60, 1);
            IPS_SetVariableProfileIcon($profileName, "Clock");
        }

        $profileName = "PAC_Status";
        IPS_DeleteVariableProfile($profileName);
        if (!IPS_VariableProfileExists($profileName)) {
            IPS_SetVariableProfileValues($profileName, 0, 7, 0);
            IPS_SetVariableProfileIcon($profileName, "Shutter");
            IPS_SetVariableProfileAssociation($profileName, 0, "Ausgeschaltet", "", $transparent);
            IPS_SetVariableProfileAssociation($profileName, 1, "Warte auf Freigabe", "", $yellow);
            IPS_SetVariableProfileAssociation($profileName, 2, "Setze Aktiv", "", $green);
            IPS_SetVariableProfileAssociation($profileName, 3, "Aktiv", "", $green);
            IPS_SetVariableProfileAssociation($profileName, 4, "Setze Pause", "", $blue);
            IPS_SetVariableProfileAssociation($profileName, 5, "Pause", "", $blue);
            IPS_SetVariableProfileAssociation($profileName, 6, "Ausschalten", "", $transparent);
            IPS_SetVariableProfileAssociation($profileName, 7, "Manuell Ein", "", $red);
        }


        //Variables
        $this->RegisterVariableInteger('OpMode', $this->Translate('Operation Mode'), 'PAC_OpMode', 0);
        $this->EnableAction('OpMode');

        $this->RegisterVariableInteger('PulseTime', $this->Translate('Pulse Time'), 'PAC_PulseTime', 10);
        $this->EnableAction('PulseTime');

        $this->RegisterVariableInteger('PauseTime', $this->Translate('Pause Time'), 'PAC_PauseTime', 20);
        $this->EnableAction('PauseTime');

        $this->RegisterVariableInteger('Status', $this->Translate('Status'), 'PAC_Status', 40);

        $this->RegisterVariableBoolean('AutomaticRelease', $this->Translate('Automatic Release'), 'PAC_Switch', 60);
        $this->EnableAction('AutomaticRelease');
        
        $this->RegisterTimer('PAC_Timer', 0, 'PAC_UpdateTimer($_IPS[\'TARGET\']);');
        $this->RegisterPropertyInteger('ExpertModeID', 0);
     
        $this->RegisterVariableIds($this->ReadAttributeString('SwitchList'));
        $this->RegisterVariableIds($this->ReadAttributeString('StatusList'));
        $this->RegisterPropertyInteger('MaxPulseTime', 60);
        $this->RegisterPropertyInteger('MaxPauseTime', 60);

        $this->RegisterPropertyBoolean('Debug', false);
      
        //Variablefür Änderungen registrieren
        //Achtung hier ID für Namen holen
        //Variable für Automatikfreigabe für Änderungen registrieren
        $this->RegisterMessage($this->GetIDForIdent('AutomaticRelease'),VM_UPDATE);
    }

  
    private function UpdateTimeProfile(string $profileName, float $maxValue, string $suffix)
    {
        IPS_SetVariableProfileText($profileName,"",$suffix);
        IPS_SetVariableProfileValues ($profileName, 0, $maxValue,1);  
    }

    private function GetSuffix ( int $suffixId)
    {
        switch ($suffixId)
        {
            case 0:
                return " sec";
            case 1:
                return " min";
            case 2:
                return " std";
        }
        return " ???";
    }



    //Wird aufgerufen bei Änderungen in der GUI, wenn für Variable
    //void EnableAction (string $Ident)
    //regstriert wird
    public function RequestAction($Ident,$Value)
    {
        switch($Ident) {
            case "OpMode":
                $this->SetValue($Ident, $Value);
                $this->HandleOpMode($Value);
                break;
            case "PulseTime":
                $this->SetValue($Ident, $Value);
                $this->RestartTimers();
                break;
            case "PauseTime":
                $this->SetValue($Ident, $Value);
                $this->RestartTimers();
                break;
            case "AutomaticRelease":
                 $this->SetValue($Ident, $Value);
                break;
        }
    }

    public function GetArrayFromString (string $itemsString)
    {
        return explode(',', $itemsString);
    }
   
  
    public function HandleOpMode(int $opmode)
    {
        switch($opmode) {
            case 0: //Aus
               
                break;
            case 1: //Handbetrieb
              
                break;
            case 2: //Automatikbetrieb
               
                break;
            default:
        }
    }


    //Wird aufgrufen wenn Variable mit
    //void RegisterMessage (integer $SenderID, integer $NachrichtID)
    //registriert wird
    public function MessageSink($TimeStamp,$SenderID,$Message,$Data)
    {
        //$Data:
        //0: Aktueller Wert
        //1: Ob es eine Differenz zum alten Wert gibt.
        //2: Alter Wert
        
        //Hier ist "OnChange" ausprogrammiert, d.h. wenn es keine Differenz zm alten Wert gibt, dann Abflug
        if ($Data[1]==0) return;
            
        //$this->SendDebug("MessageSink", "Message from SenderID ".$SenderID." with Message ".$Message."\r\n Data: ".print_r($Data, true), 0);
        if ($this->ReadPropertyBoolean('Debug')) {
            IPS_LogMessage("MessageSink", 'id:'.$SenderID.' message:'.$Message.' data:'.print_r($Data, true));
        }
        
        if ($this->ReadPropertyInteger('ExpertModeID') == $SenderID)
        {
            $this->HandleExpertSwitch($SenderID);
        }
        else if ($this->GetIDForIdent('AutomaticRelease') == $SenderID)
        {
            if ($this->ReadPropertyBoolean('Debug')) {
                IPS_LogMessage("MessageSink", 'id:'.$SenderID.' message:'.$Message);
            }
             $this->AutomaticRelease();
        }
        //Die Statusänderung des Actors auswerten
        else
        {
            $this-> StatusUpdate($SenderID);
        }  
    }

    public function UpdateTimer()
    {
    }
    
    
    private function AutomaticRelease ()
    {
    }

    private function StatusUpdate ($senderID)
    {
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

         $itemsString = $this->ReadAttributeString('StatusList');
        foreach ( $this->GetArrayFromString($this->ReadAttributeString('StatusList')) as $item) {
            $this->RegisterStatusUpdate($item);
        }

        $this->RegisterStatusUpdate('ExpertModeID');
      
        //TimeProfile aufdatieren
        $this->UpdateTimeProfile("PAC_PulseTime", $this->ReadPropertyInteger('MaxPulseTime'),$this->GetSuffix($this->ReadPropertyInteger('PulseTimeUnit')));
        $this->UpdateTimeProfile("PAC_PauseTime", $this->ReadPropertyInteger('MaxPauseTime'),$this->GetSuffix($this->ReadPropertyInteger('PauseTimeUnit')));
      
    }

    //Methode Registriert Variable für die MessageSink, soferne dieser in der Modul-Form aktiviert ist
    public function RegisterStatusUpdate(string $statusName)
    {
        $id= $this->ReadPropertyInteger($statusName);
        //Register for change notification if a variable is defined
        //IPS_LogMessage("ApplyChanges", 'id:'.$id.' name:'.$statusName);
        if ($id>0) {
            $this->RegisterMessage($id,VM_UPDATE);
        }
    }


    public function RestartTimers()
    {
        switch($this->GetValue('OpMode')) 
        {
            case 2: //Automatikbetrieb
                if (!$this->GetValue('AutomaticRelease')) return;

                /*
                $mainColorNumbers = array_map('intval', explode(',', $this->ReadAttributeString('MainColorList')));
                $fadeTime =  $this->GetValue("ColorFadeTime") * 1000;
                $setInterval =  $this->GetValue("ColorChangeTime") * 1000 *60;
                $actColor =  $this->ReadAttributeInteger("ActColor");
                //Falls Haupfarbe aktiv ist, dann normale Wechselzeit starten
                if (in_array($actColor, $mainColorNumbers, true)) {
                    $this->SetTimerInterval('LCC_Timer', $setInterval);
                }
                //ansonsten Übergangszeit neu starten falls grösser 0
                else if ($fadeTime > 0) {
                    $this->SetTimerInterval('LCC_Timer', $fadeTime);
                }
                //ansonsten auf nächste Farbe schalten
                else
                {
                    $this->ChangeColor();
                }
                */
                break;
        }
    }
     

    //Methode setzt Variable, soferne dieser in der Modul-Form aktiviert ist
    public function SetDevice(string $switchName, bool $status)
    {
        $id= $this->ReadPropertyInteger($switchName);
        
        if ($id>0) {
            RequestAction($id, $status);
        }
    }
    
    private function HandleExpertSwitch(int $id)
    {
        $status = !GetValueBoolean($id);
        if ($id==0)  $status = false;
        //IPS_LogMessage("HandleExpertSwitch", 'id:'.$id.' value:'.$status);
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
    }
    
    private function HideItem(string $item,bool $status)
    {
        $id = $this->GetIDForIdent($item);
        IPS_SetHidden($id, $status);
    }
    
    private function LockItem(string $item,bool $status)
    {
        $id = $this->GetIDForIdent($item);
        IPS_SetDisabled($id, $status);
    }
        
}
