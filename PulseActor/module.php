<?php

declare(strict_types=1);
class PulseActor extends IPSModule
{
    private const Aus = 0;
    private const Manuell = 1;
    private const Automatik = 2;
    private const Ausgeschaltet = 0;
    private const WarteAufFreigabe = 1;
    private const SetzeAktiv = 2;
    private const Aktiv = 3;
    private const SetzePause = 4;
    private const Pause = 5;
    private const Ausschalten = 6;
    private const ManuellAktiv = 7;

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
        $this->RegisterAttributeString('SwitchList', "SwitchActorID");
        $this->RegisterAttributeString('StatusList', "StatusActorID");
        $this->RegisterAttributeString('ExpertListHide',"");
        $this->RegisterAttributeString('ExpertListLock',"OpMode,PulseTime,PauseTime");

        //Profiles
        $profileName = "PAC_Switch";
        if (IPS_VariableProfileExists($profileName)) IPS_DeleteVariableProfile($profileName);
        if (!IPS_VariableProfileExists($profileName)) {
            IPS_CreateVariableProfile($profileName, 0);
            IPS_SetVariableProfileIcon($profileName, "Power");
            IPS_SetVariableProfileAssociation($profileName, false, "Aus", "", $transparent);
            IPS_SetVariableProfileAssociation($profileName, true, "Ein", "", $green);
        }

        $profileName = "PAC_OpMode";
        if (IPS_VariableProfileExists($profileName)) IPS_DeleteVariableProfile($profileName);
        if (!IPS_VariableProfileExists($profileName)) {
            IPS_CreateVariableProfile($profileName, 1);
            IPS_SetVariableProfileValues($profileName, 0, 2, 0);
            IPS_SetVariableProfileIcon($profileName, "Shutter");
            IPS_SetVariableProfileAssociation($profileName, 0, "Aus", "", $transparent);
            IPS_SetVariableProfileAssociation($profileName, 1, "Hand", "", $yellow);
            IPS_SetVariableProfileAssociation($profileName, 2, "Automatik", "", $green);
        }

        $profileName = "PAC_PulseTime";
        if (IPS_VariableProfileExists($profileName)) IPS_DeleteVariableProfile($profileName);
        if (!IPS_VariableProfileExists($profileName)) {
            IPS_CreateVariableProfile($profileName, 1);
            IPS_SetVariableProfileText($profileName, "", " sec");
            IPS_SetVariableProfileValues($profileName, 1, 60, 1);
            IPS_SetVariableProfileIcon($profileName, "Clock");
        }

        $profileName = "PAC_PauseTime";
        if (IPS_VariableProfileExists($profileName)) IPS_DeleteVariableProfile($profileName);
        if (!IPS_VariableProfileExists($profileName)) {
            IPS_CreateVariableProfile($profileName,1);
            IPS_SetVariableProfileText($profileName, "", " sec");
            IPS_SetVariableProfileValues($profileName, 0, 60, 1);
            IPS_SetVariableProfileIcon($profileName, "Clock");
        }

        $profileName = "PAC_Status";
        if (IPS_VariableProfileExists($profileName)) IPS_DeleteVariableProfile($profileName);
        if (!IPS_VariableProfileExists($profileName)) {
            IPS_CreateVariableProfile($profileName,1);
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

        $this->RegisterTimer('PAC_PulseTimer', 0, 'PAC_UpdatePulseTimer($_IPS[\'TARGET\']);');
        $this->RegisterTimer('PAC_PauseTimer', 0, 'PAC_UpdatePauseTimer($_IPS[\'TARGET\']);');
        $this->RegisterTimer('PAC_SignalCheckTimer', 0, 'PAC_VerifySignal($_IPS[\'TARGET\']);');

        $this->RegisterPropertyInteger('ExpertModeID', 0);

        $this->RegisterVariableIds($this->ReadAttributeString('SwitchList'));
        $this->RegisterVariableIds($this->ReadAttributeString('StatusList'));
        $this->RegisterPropertyInteger('MaxPulseTime', 60);
        $this->RegisterPropertyInteger('MaxPauseTime', 60);
        $this->RegisterPropertyInteger('PulseTimeUnit',0);
        $this->RegisterPropertyInteger('PauseTimeUnit',0);
        $this->RegisterPropertyBoolean('Debug', false);

        $this->RegisterAttributeInteger('PulseTimeFactor',0);
        $this->RegisterAttributeInteger('PauseTimeFactor',0);

        //Variablefür Änderungen registrieren
        //Achtung hier ID für Namen holen
        //Variable für Automatikfreigabe für Änderungen registrieren
        $this->RegisterMessage($this->GetIDForIdent('AutomaticRelease'),VM_UPDATE);
    }

    public function RegisterVariableIds(string $itemsString)
    {
        foreach (explode(',', $itemsString) as $item) {
            if ($item != "") $this->RegisterPropertyInteger($item, 0);
        }
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

    private function GetTimerFactor ( int $suffixId)
    {
        switch ($suffixId)
        {
            case 0:
                return 1;
            case 1:
                return 60;
            case 2:
                return 3600;
        }
        return 1;
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
            case self::Aus: //Aus
                $this->HideItem("AutomaticRelease",true);
                $this->PulseAction ();
                break;
            case self::Manuell: //Handbetrieb
                $this->HideItem("AutomaticRelease",true);
                $this->PulseAction ();
                break;
            case self::Automatik: //Automatikbetrieb
                $this->HideItem("AutomaticRelease",false);
                $this->PulseAction ();
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

    public function UpdatePulseTimer()
    {
        $this->PulseAction ();
    }

    public function UpdatePauseTimer()
    {
        $this->PulseAction ();
    }

    private function AutomaticRelease ()
    {
        $this->PulseAction ();
    }

    private function StatusUpdate ($senderID)
    {
        $itemArray =  $this->GetArrayFromString ($this->ReadAttributeString('StatusList'));
        $idx = 0;
        $found = false;
        foreach ($itemArray as $item)
        {
            if ($senderID == $this->ReadPropertyInteger($item))
            {
                $found = true;
                break;
            }
            $idx++;
        }
        if (!$found) return;
        VerifySignal();
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

        $this->WriteAttributeInteger('PulseTimeFactor',$this->GetTimerFactor($this->ReadPropertyInteger('PulseTimeUnit')));
        $this->WriteAttributeInteger('PauseTimeFactor',$this->GetTimerFactor($this->ReadPropertyInteger('PauseTimeUnit')));

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
                if (!$this->IsReleased()) return;
                break;
        }
    }

    //Methode setzt Variable, soferne dieser in der Modul-Form aktiviert ist
    public function SetDevice(string $switchName, bool $status)
    {
        $id= $this->ReadPropertyInteger($switchName);

        if ($id>0) {
            RequestAction($id, $status);
            StartSignalChecker();
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

    private function VerifySignal()
    {
        $idSet = $this->ReadPropertyInteger("SwitchActorID");
        if ($idSet == 0) return;
        $idAct = $this->ReadPropertyInteger("StatusActorID");
        if ($idAct == 0) return;

        $statusSet = GetValueBoolean($idSet);
        if ($this->CheckSignal($idSet,$statusSet, $idAct))
        {
            $this->StopSignalChecker();
            return;
        }
        $logStatus = $statusSet ? "EIN" : "AUS";
        $logMessage = "Setze ".GetName(GetParent($idSet))." erneut auf: ".$logStatus;
        IPS_LogMessage("PulseActor-SetSignal",$logMessage);
        RequestAction($idSet,$statusSet);
        $this->StartSignalChecker();
    }

    private function StopSignalChecker()
    {
        $this->SetTimerInterval('PAC_SignalCheckTimer', 0);
    }
    private function StartSignalChecker()
    {
        $this->SetTimerInterval('PAC_SignalCheckTimer', 3);
    }

    private function CheckSignal(int $idSet,bool $statusSet,int $idAct)
    {
        $statusAct = GetValueBoolean($idAct);
        if ($statusSet ==  $statusAct)
        {
            return true;
        }

        EIB_RequestStatus (GetParent($idAct));
        $statusAct = GetValueBoolean($idAct);
        return ($statusSet ==  $statusAct);
    }

    private function PulseAction ()
    {
        $betriebsart = $this->GetValue('OpMode');
        $action =  $this->GetValue('Status');
        $actAction = $action;

        switch ($betriebsart)
        {
            case self::Aus:
                $action = self::Ausschalten;
		        break;
            case self::Manuell:
		        $action = self::ManuellAktiv;
		        break;
            case self::Automatik:
		        break;
	        default:
		        break;
        }

        $action = $this->SetAction($action);
        if ($action != $actAction)
        {
            $this->SetValue('Status',$action);
        }
        IPS_LogMessage("PulsActor.PulseAction",'Status: '.$action);
    }

    private function SwitchOff ()
    {
        $this->StopTimer();
        $this->SetDevice("SwitchActorID",false);
    }

    private function SwitchOn ()
    {
        $this->SetDevice("SwitchActorID",true);
    }

    private function SetAction ($action)
    {
	    $actAction = $action;

	    switch ($action)
	    {
            case self::Ausgeschaltet:
			    break;
		    case  self::WarteAufFreigabe:
                $this->SwitchOff ();
			    if ($this->IsReleased())
			    {
				    $actAction =  $this->SetAction ( self::SetzeAktiv);
			    }
			    break;
		    case  self::SetzeAktiv:
                $this->StopTimer();
                $this->StartPulseTime();
			    $this->SwitchOn;
			    $actAction =  self::Aktiv;
			    break;
		    case  self::Aktiv:
                if ($this->IsReleased())
			    {
			        $actAction =  $this->SetAction ( self::SetzePause);
                }
                else
                {
                    $actAction =  $this->SetAction ( self::WarteAufFreigabe);
                }
			    break;
		    case  self::SetzePause:
			    $this->SwitchOff ();
                $this->StartPauseTime();
			    $actAction =  self::Pause;
			    break;
		    case  self::Pause:
                if ($this->IsReleased())
                {
                    $actAction = $this->SetAction ( self::SetzeAktiv);
                }
                else
                {
                    $actAction = $this->SetAction ( self::WarteAufFreigabe);
                }
			    break;
		    case  self::Ausschalten:
			    $this->SwitchOff();
			    $actAction =   self::Ausgeschaltet;
			    break;
		    case  self::ManuellAktiv:
                $this->StopTimer();
                $this->SwitchOn();
			    break;
		    default:
			    break;
	    }

	    return $actAction;
    }

    private function IsReleased()
    {
        return $this->GetValue('AutomaticRelease');
    }

    private function StartPauseTime ()
    {
        $pauseTime =  $this->GetValue('PauseTime') * $this->ReadAttributeInteger('PauseTimeFactor');
        $this->SetTimerInterval('PAC_PauseTimer', $pauseTime);
    }
    private function StartPulseTime ()
    {
        $pulseTime =  $this->GetValue('PulseTime') * $this->ReadAttributeInteger('PulseTimeFactor');
        $this->SetTimerInterval('PAC_PulseTimer', $pulseTime );
    }
    private function Stoptimer ()
    {
        $this->SetTimerInterval('PAC_PauseTimer', 0);
        $this->SetTimerInterval('PAC_PulseTimer', 0);
    }

}