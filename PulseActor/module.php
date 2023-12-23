<?php

declare(strict_types=1);
include_once __DIR__ . '../../libs/RBH_ModuleFunctions.php';

class PulseActor extends IPSModule
{
    use RBH_ModuleFunctions;

    //Operation Modi
    private const Aus = 0;
    private const Manuell = 1;
    private const Automatik = 2;

    //Action Stati
    private const Ausgeschaltet = 0;
    private const WarteAufFreigabe = 1;
    private const SetzeAktiv = 2;
    private const Aktiv = 3;
    private const SetzePause = 4;
    private const Pause = 5;
    private const Ausschalten = 6;
    private const ManuellAktiv = 7;
    private const SetzeDauerAktiv = 8;
    private const DauerAktiv = 9;

    private const MODULE_PREFIX = 'PAC';
    private const MODULE_NAME = 'PulseActor';

    private const ProfileList = 'AutomaticRelease,PulseTime,PauseTime,OpMode,ModuleStatus,AutomaticContinued';

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
        $this->RegisterAttributeString('ExpertListHide',"AutomaticContinued");
        $this->RegisterAttributeString('ExpertListLock',"OpMode,PulseTime,PauseTime");

        $this->DeleteProfileList (self::ProfileList);

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


        //PulseTime
        $variable = 'PulseTime';
        $profileName =  $this->CreateProfileName($variable);
        if (!IPS_VariableProfileExists($profileName)) {
            IPS_CreateVariableProfile($profileName, 1);
            IPS_SetVariableProfileText($profileName, "", " sec");
            IPS_SetVariableProfileValues($profileName, 1, 60, 1);
            IPS_SetVariableProfileIcon($profileName, "Clock");
        }
        $this->RegisterVariableInteger($variable, $this->Translate('Pulse Time'), $profileName, 10);
        $this->EnableAction($variable);

        //PauseTime
        $variable = 'PauseTime';
        $profileName =  $this->CreateProfileName($variable);
        if (!IPS_VariableProfileExists($profileName)) {
            IPS_CreateVariableProfile($profileName,1);
            IPS_SetVariableProfileText($profileName, "", " sec");
            IPS_SetVariableProfileValues($profileName, 0, 60, 1);
            IPS_SetVariableProfileIcon($profileName, "Clock");
        }
        $this->RegisterVariableInteger($variable, $this->Translate('Pause Time'), $profileName, 20);
        $this->EnableAction($variable);

        //ModuleStatus
        $variable = 'ModuleStatus';
        $profileName =  $this->CreateProfileName($variable);
        if (!IPS_VariableProfileExists($profileName)) {
            IPS_CreateVariableProfile($profileName,1);
            IPS_SetVariableProfileValues($profileName, 0, 7, 0);
            IPS_SetVariableProfileIcon($profileName, "Information");
            IPS_SetVariableProfileAssociation($profileName, 0, "Ausgeschaltet", "", $transparent);
            IPS_SetVariableProfileAssociation($profileName, 1, "Warte auf Freigabe", "", $yellow);
            IPS_SetVariableProfileAssociation($profileName, 2, "Setze Aktiv", "", $green);
            IPS_SetVariableProfileAssociation($profileName, 3, "Aktiv", "", $green);
            IPS_SetVariableProfileAssociation($profileName, 4, "Setze Pause", "", $blue);
            IPS_SetVariableProfileAssociation($profileName, 5, "Pause", "", $blue);
            IPS_SetVariableProfileAssociation($profileName, 6, "Ausschalten", "", $transparent);
            IPS_SetVariableProfileAssociation($profileName, 7, "Manuell Ein", "", $red);
        }
        $this->RegisterVariableInteger($variable , $this->Translate('Status'), $profileName, 40);

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
        $this->RegisterMessage($this->GetIDForIdent($variable), VM_UPDATE);


        //AutomaticContinued
        $variable = 'AutomaticContinued';
        $profileName = $this->CreateProfileName($variable);
        if (!IPS_VariableProfileExists($profileName)) {
            IPS_CreateVariableProfile($profileName, 0);
            IPS_SetVariableProfileIcon($profileName, "Ok");
            IPS_SetVariableProfileAssociation($profileName, false, "Aus", "", $transparent);
            IPS_SetVariableProfileAssociation($profileName, true, "Ein", "", $green);
        }
        $this->RegisterVariableBoolean($variable, $this->Translate('Automatic Continued'), $profileName, 80);
        $this->EnableAction($variable);
        //Variable für Änderungen registrieren
        $this->RegisterMessage($this->GetIDForIdent($variable), VM_UPDATE);


        //------------------------------------------------------------------------------------------------------------------
        //Timer ------------------------------------------------------------------------------------------------------------
        $this->RegisterTimer('PAC_PulseTimer', 0, 'PAC_UpdatePulseTimer($_IPS[\'TARGET\']);');
        $this->RegisterTimer('PAC_PauseTimer', 0, 'PAC_UpdatePauseTimer($_IPS[\'TARGET\']);');
        $this->RegisterTimer('PAC_SignalCheckTimer', 0, 'PAC_VerifySignal($_IPS[\'TARGET\']);');
        //------------------------------------------------------------------------------------------------------------------

        $this->RegisterPropertyInteger('ExpertModeID', 0);
        $this->RegisterPropertyInteger('MaxPulseTime', 60);
        $this->RegisterPropertyInteger('MaxPauseTime', 60);
        $this->RegisterPropertyInteger('PulseTimeUnit',0);
        $this->RegisterPropertyInteger('PauseTimeUnit',0);
        $this->RegisterPropertyBoolean('CheckActor', false);

        $this->RegisterAttributeInteger('PulseTimeFactor',0);
        $this->RegisterAttributeInteger('PauseTimeFactor',0);

        $this->RegisterVariableIds($this->ReadAttributeString('SwitchList'));
        $this->RegisterVariableIds($this->ReadAttributeString('StatusList'));
    }

    private function UpdateTimeProfile(string $profileName, float $maxValue, string $suffix) : void
    {
        IPS_SetVariableProfileText($profileName,"",$suffix);
        IPS_SetVariableProfileValues ($profileName, 0, $maxValue,1);
    }

    private function GetSuffix ( int $suffixId) : string
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

    private function GetTimerFactor ( int $suffixId) : int
    {
        switch ($suffixId)
        {
            case 0:
                return 1000;
            case 1:
                return 60000;
            case 2:
                return 3600000;
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
            case "AutomaticContinued":
                $this->SetValue($Ident, $Value);
                break;
        }
    }

    public function HandleOpMode(int $opmode) : void
    {
        switch($opmode) {
            case self::Aus: //Aus
                $this->HideItem("AutomaticRelease",true);
                $this->HideItem("AutomaticContinued", true);
                $this->PulseAction ();
                break;
            case self::Manuell: //Handbetrieb
                $this->HideItem("AutomaticRelease",true);
                $this->HideItem("AutomaticContinued", true);
                $this->PulseAction ();
                break;
            case self::Automatik: //Automatikbetrieb
                $this->HideItem("AutomaticRelease",false);
                $this->HideItem("AutomaticContinued", false);
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

        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        //$this->SendDebug(__FUNCTION__, 'id:'.$SenderID.' message:'.$Message.' data:'.print_r($Data, true), 0);

        if ($this->ReadPropertyInteger('ExpertModeID') == $SenderID)
        {
            $this->HandleExpertSwitch($SenderID,$this->ReadAttributeString('ExpertListHide'),$this->ReadAttributeString('ExpertListLock'));
        }
        else if ($this->GetIDForIdent('AutomaticRelease') == $SenderID)
        {
            $this->SendDebug(__FUNCTION__,'id:'.$SenderID.' message:'.$Message, 0);
            $this->AutomaticRelease();
        }
        else if ($this->GetIDForIdent('AutomaticContinued') == $SenderID)
        {
            $this->SendDebug(__FUNCTION__, 'id:' . $SenderID . ' message:' . $Message, 0);
            $this->AutomaticRelease();
        }
        //Die Statusänderung des Actors auswerten
        else
        {
            $this->StatusUpdate($SenderID);
        }
    }

    public function UpdatePulseTimer() : void
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);;
        $this->PulseAction ();
    }

    public function UpdatePauseTimer() : void
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        $this->PulseAction ();
    }

    private function AutomaticRelease () : void
    {
        $this->PulseAction ();
    }

    private function StatusUpdate (int $senderID) : void
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

        $this->VerifySignal();
    }

    public function Destroy()
    {
        //Never delete this line!
        parent::Destroy();


        $this->DeleteProfileList (self::ProfileList);
    }


    //Wird aufgerufen, wenn in der Form für das Module was geändert wurde und das "Änderungen Übernehmen" bestätigt wird.
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

        $this->RegisterPropertiesUpdateList( $this->ReadAttributeString('StatusList'));

        //Link auf "StatusActorID" variable erzeugen, falls noch nicht existiert
        $targetID = $this->ReadPropertyInteger('StatusActorID');
        $linkID = @IPS_GetLinkIDByName('Gerätestatus', $this->InstanceID);
        if ($targetID != 0 && @IPS_ObjectExists($targetID)) {
            //Check for existing link
            if (!is_int($linkID) && !$linkID) {
                $linkID = IPS_CreateLink();
            }
            IPS_SetParent($linkID, $this->InstanceID);
            IPS_SetPosition($linkID, 90);
            IPS_SetName($linkID, 'Gerätestatus');
            IPS_SetIcon($linkID, 'Electricity');
            IPS_SetLinkTargetID($linkID, $targetID);
        } else {
            if (is_int($linkID)) {
                IPS_SetHidden($linkID, true);
            }
        }

        $this->RegisterStatusUpdate('ExpertModeID');
        $this->RegisterMessage($this->GetIDForIdent('AutomaticRelease'),VM_UPDATE);
        $this->RegisterMessage($this->GetIDForIdent('AutomaticContinued'), VM_UPDATE);

        $this->WriteAttributeInteger('PulseTimeFactor',$this->GetTimerFactor($this->ReadPropertyInteger('PulseTimeUnit')));
        $this->WriteAttributeInteger('PauseTimeFactor',$this->GetTimerFactor($this->ReadPropertyInteger('PauseTimeUnit')));

        //TimeProfile aufdatieren
        $this->UpdateTimeProfile($this->CreateProfileName("PulseTime"), $this->ReadPropertyInteger('MaxPulseTime'),$this->GetSuffix($this->ReadPropertyInteger('PulseTimeUnit')));
        $this->UpdateTimeProfile($this->CreateProfileName("PauseTime"), $this->ReadPropertyInteger('MaxPauseTime'),$this->GetSuffix($this->ReadPropertyInteger('PauseTimeUnit')));

        $this->RegisterReferenceVarIdList($this->ReadAttributeString('SwitchList'));
        $this->RegisterReferenceVarIdList($this->ReadAttributeString('StatusList'));
        $this->RegisterReferenceVarId($this->ReadPropertyInteger('ExpertModeID'));

    }

    public function RestartTimers() : void
    {
        switch($this->GetValue('OpMode'))
        {
            case 2: //Automatikbetrieb
                if (!$this->IsReleased()) return;
                break;
        }
    }

    //Methode setzt Variable, soferne dieser in der Modul-Form aktiviert ist
    public function SetDevice(string $switchName, bool $status) : void
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);

        $id = $this->ReadPropertyInteger($switchName);
        $this->SendDebug(__FUNCTION__, "switchName: ".$switchName." id: ".$id, 0);

        if ($id>1) {
            RequestAction($id, $status);
            $this->StartSignalChecker();
        }
    }

    public function VerifySignal() : void
    {
        if (!$this->ReadPropertyBoolean('CheckActor')) return;
        $idSet = $this->ReadPropertyInteger("SwitchActorID");
        if ($idSet < 2) return;
        $idAct = $this->ReadPropertyInteger("StatusActorID");
        if ($idAct < 2) return;

        $statusSet = GetValueBoolean($idSet);
        if ($this->CheckSignal($idSet,$statusSet, $idAct))
        {
            $this->StopSignalChecker();
            return;
        }
        $logStatus = $statusSet ? "EIN" : "AUS";
        $logMessage = "Setze ".IPS_GetName(IPS_GetParent($idSet))." erneut auf: ".$logStatus;
        IPS_LogMessage("PulseActor.VerifySignal",$logMessage);
        RequestAction($idSet,$statusSet);
        $this->StartSignalChecker();
    }

    private function StopSignalChecker() : void
    {
        $this->SetTimerInterval('PAC_SignalCheckTimer', 0);
    }
    private function StartSignalChecker()
    {
        $this->SetTimerInterval('PAC_SignalCheckTimer', 3000);
    }

    private function CheckSignal(int $idSet,bool $statusSet,int $idAct) :bool
    {
        $statusAct = GetValueBoolean($idAct);
        if ($statusSet ==  $statusAct)
        {
            return true;
        }
        $parentId = IPS_GetParent($idAct);

        if (!KNX_RequestStatus($parentId))
        {
            EIB_RequestStatus ($parentId);
        }

        $statusAct = GetValueBoolean($idAct);
        return ($statusSet ==  $statusAct);
    }

    private function PulseAction () : void
    {
        $betriebsart = $this->GetValue('OpMode');
        $action =  $this->GetValue('ModuleStatus');
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
                $action = $this->GetAutomaticAction ($action);
		        break;
        }

        $action = $this->SetAction($action);
        if ($action != $actAction)
        {
            $this->SetValue('ModuleStatus',$action);
        }
        IPS_LogMessage("PulsActor.PulseAction",'ModuleStatus: '.$action);
    }

    private function GetAutomaticAction (int $action) : int
    {
        switch ($action)
        {
            case self::Ausgeschaltet:
            case self::ManuellAktiv:
                $action = self::WarteAufFreigabe;
                break;
        }
        return $action;
    }

    private function SwitchOff () : void
    {
        $this->StopTimer();
        $this->SetDevice("SwitchActorID",false);
    }

    private function SwitchOn () : void
    {
        $this->SetDevice("SwitchActorID",true);
    }

    private function SetAction (int $action) : int
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
                     if ($this->IsContinuedMode())
                     {
                        $actAction = $this->SetAction(self::SetzeDauerAktiv);
                     }
				     else if ($this->GetPulseTime() > 0) $actAction =  $this->SetAction ( self::SetzeAktiv);
			    }
			    break;
		    case  self::SetzeAktiv:
                $this->StopTimer();
                $this->StartPulseTime();
			    $this->SwitchOn();
			    $actAction =  self::Aktiv;
			    break;
		    case  self::Aktiv:
                if ($this->IsReleased())
			    {
                    if ($this->GetPauseTime() > 0) $actAction =  $this->SetAction ( self::SetzePause);
                }
                else
                {
                    $actAction =  $this->SetAction ( self::WarteAufFreigabe);
                }
			    break;
            case self::SetzeDauerAktiv:
                $this->StopTimer();
                $this->SwitchOn();
                $actAction = self::DauerAktiv;
                break;
            case self::DauerAktiv:
                if (!$this->IsReleased())
                {
                    $actAction = $this->SetAction(self::WarteAufFreigabe);
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

    private function IsReleased() : bool
    {
        return $this->GetValue('AutomaticRelease');
    }

    private function IsContinuedMode() : bool
    {
        return $this->GetValue('AutomaticContinued');
    }

    private function StartPauseTime () : bool
    {
        $pauseTime =   $this->GetPauseTime ();
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        $this->SendDebug(__FUNCTION__, 'PauseTime: '.$pauseTime, 0);
        $this->SetTimerInterval('PAC_PauseTimer', $pauseTime);
        return $pauseTime > 0;
    }
    private function StartPulseTime () : bool
    {
        $pulseTime =  $this->GetPulseTime();
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        $this->SendDebug(__FUNCTION__, 'PulseTime: '.$pulseTime, 0);
        $this->SetTimerInterval('PAC_PulseTimer', $pulseTime );
        return $pulseTime > 0;
    }

    public function GetPulseTime () : int
    {
        return $this->GetValue('PulseTime') * $this->ReadAttributeInteger('PulseTimeFactor');
    }

    public function GetPauseTime () : int
    {
        return $this->GetValue('PauseTime') * $this->ReadAttributeInteger('PauseTimeFactor');
    }

    private function Stoptimer () : void
    {
        $this->SetTimerInterval('PAC_PauseTimer', 0);
        $this->SetTimerInterval('PAC_PulseTimer', 0);
    }

}