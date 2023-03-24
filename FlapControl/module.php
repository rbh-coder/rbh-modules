<?php

declare(strict_types=1);
include_once __DIR__ . '../../libs/RBH_ModuleFunctions.php';

class FlapControl extends IPSModule
{
    use RBH_ModuleFunctions;

    private const MODULE_PREFIX = 'FLP';
    private const MODULE_NAME = 'FlapControl';


    private const Stop = 0;
    private const FullClose = 1;
    private const FullOpen = 2;
    private const AutoOpen = 3;

    private const Flap_Undef = 0; 
    private const Flap_Closed = 1;
    private const Flap_Close = 2;
    private const Flap_StopClose = 3;
    private const Flap_Opened = 4;
    private const Flap_Open = 5;
    private const Flap_FullOpened = 6;
    private const Flap_FullOpen = 7;
    private const Flap_StopOpen = 8;
    private const Flap_StopFullOpen = 9;
    private const Flap_CloseOpen = 10;
    private const Flap_StopAll = 11;

    private const ProfileList =                     'FlapAction,FlapStatus';
    private const RegisterVariablesUpdateList =     'FlapAction';
    private const RegisterReferenciesUpdateList =   'ExpertModeID';
    private const ReferenciesList =                 'FlapOpenActorID,FlapCloseActorID';
    private const ExpertLockList =                  '';
    private const ExpertHideList =                  '';
    

    
    private const Transparent = 0xffffff00;
    private const Red = 0xFF0000;
    private const Yellow = 0xFFFF00;
    private const Green=0x00FF00;
    private const Blue=0x0000FF;
    private const DarkGreen = 0x24F065;
    private const DarkBlue  = 0x0D0FE4;

    public function Create()
    {
        //Never delete this line!
        parent::Create();

        $this->RegisterPropertyInteger('ExpertModeID', 0);
        $this->RegisterPropertyInteger('MaxClosingTime', 20);
        $this->RegisterPropertyInteger('MaxOpeningTime', 20);
        $this->RegisterPropertyInteger('OpeningTime', 9);

        $this->DeleteProfileList (self::ProfileList);

        //Variablen --------------------------------------------------------------------------------------------------------
        //FlapAction
        $variable = 'FlapAction';
        $profileName =  $this->CreateProfileName($variable);
        if (!IPS_VariableProfileExists($profileName)) {
            IPS_CreateVariableProfile($profileName, 1);
            IPS_SetVariableProfileValues($profileName, 0, 2, 0);
            IPS_SetVariableProfileIcon($profileName, "Shutter");
            IPS_SetVariableProfileAssociation($profileName, 0, "Stop", "", self::Transparent);
            IPS_SetVariableProfileAssociation($profileName, 1, "Ganz zu", "",  self::Green);
            IPS_SetVariableProfileAssociation($profileName, 2, "Ganz auf", "",  self::Green);
            IPS_SetVariableProfileAssociation($profileName, 3, "Auto auf", "",  self::Green);
        }
        $this->RegisterVariableInteger($variable, $this->Translate('Flap Action'),$profileName, 10);
        $this->EnableAction($variable);

        $variable = 'FlapStatus';
        $profileName =  $this->CreateProfileName($variable);
        if (!IPS_VariableProfileExists($profileName)) {
            IPS_CreateVariableProfile($profileName,1);
            IPS_SetVariableProfileValues($profileName, 0, 11, 0);
            IPS_SetVariableProfileIcon($profileName, "Shutter");
            IPS_SetVariableProfileAssociation($profileName, 0, "Zwischenstellung", "", self::Transparent);
            IPS_SetVariableProfileAssociation($profileName, 1, "Geschlossen", "", self::Transparent);
            IPS_SetVariableProfileAssociation($profileName, 2, "Schließen", "", self::Transparent);
            IPS_SetVariableProfileAssociation($profileName, 3, "Schließen Stop", "", self::Transparent);
            IPS_SetVariableProfileAssociation($profileName, 4, "Offen", "", self::Transparent);
            IPS_SetVariableProfileAssociation($profileName, 5, "Öffnen", "", self::Transparent);
            IPS_SetVariableProfileAssociation($profileName, 6, "Ganz Offen", "", self::Transparent);
            IPS_SetVariableProfileAssociation($profileName, 7, "Ganz Öffnen", "", self::Transparent);
            IPS_SetVariableProfileAssociation($profileName, 8, "Stop Öffnen", "", self::Transparent);
            IPS_SetVariableProfileAssociation($profileName, 9, "Stop ganz Öffnen", "", self::Transparent);
            IPS_SetVariableProfileAssociation($profileName, 10, "Schließen dann Öffnen", "", self::Transparent);
            IPS_SetVariableProfileAssociation($profileName, 11, "Alles stoppen", "", self::Transparent);
        }
        $this->RegisterVariableInteger($variable , $this->Translate('Flap Status'), $profileName, 20);

        //------------------------------------------------------------------------------------------------------------------
        //Timer ------------------------------------------------------------------------------------------------------------
        $this->RegisterTimer('FLP_FlapTimer', 0, 'FLP_UpdateFlapTimer($_IPS[\'TARGET\']);');
        //------------------------------------------------------------------------------------------------------------------

        $this->RegisterVariableIds(self::ReferenciesList);
    }


    //Wird aufgerufen bei Änderungen in der GUI, wenn für Variable
    //void EnableAction (string $Ident)
    //regstriert wird
    public function RequestAction($Ident,$Value)
    {
        switch($Ident) {
              case "FlapAction":
                   $this->SetValue($Ident, $Value);
                   //$this->OperateFlapAction($Value);
                   break;
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

         $this->SendDebug(__FUNCTION__, 'Wert hat sich auf ' . $Data[0] . ' geändert.', 0);

        if ($this->SelectFlapAction($SenderID))
        {
            $this->OperateFlapAction($Data[0]);
            return; 
        }

        if ($this->SelectExpertSwitch($SenderID))
        {
            $this->OperateExpertSwitch($SenderID);
            return;
        }
    }

    private function SelectFlapAction(int $sender) : bool
    {
        $id = $this->GetIDForIdent('FlapAction');
        if (!$this->IsValidId($id)) return false;
        if ($id != $sender) return false;
        return true;
    }

    private function SelectExpertSwitch(int $sender) : bool
    {
        $id = $this->ReadPropertyInteger('ExpertModeID');
        if (!$this->IsValidId($id)) return false;
        if ($id != $sender) return false;
        return true;
    }

    private function OperateFlapAction(int $value) : void
    {
        $flapStatus =  $this->GetValue('FlapStatus');
        $actAction =  $flapStatus;
        switch ($value)
        {
            case  self::Stop:
                if ($this->DoStopAction ($flapStatus))
                {
                     $actAction = $this-> SetAction(self::Flap_StopAll);
                }
                break;
            case self::FullClose: 
                if ($flapStatus != self::Flap_Closed)
                {
                    $actAction = $this-> SetAction(self::Flap_Close);
                }

                break;
            case self::FullOpen:
                if ($flapStatus != self::Flap_FullOpened)
                {
                    $actAction = $this-> SetAction(self::Flap_FullOpen);
                }
                break;
            case self::AutoOpen:
                if (($flapStatus != self::Flap_Opened) && ($flapStatus != self::Flap_Opened))
                {
                    $actAction = $this-> SetAction(self::Flap_CloseOpen);
                }
                break;
        }
        $this->SetValue('FlapStatus',$actAction);
    }

    private function OperateExpertSwitch(int $id) : void
    {
        $this->HandleExpertSwitch($id,self::ExpertHideList,self::ExpertLockList);
    }

    public function UpdateFlapTimer() : void
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        $action =  $this->GetValue('FlapStatus');
        $actAction = $action;
        switch ($status)
        {
            case  self::Flap_Close:
                $actAction = $this-> SetAction(self::StopClose);
                break;
            case  self::Flap_Open:
                $actAction = $this-> SetAction(self::StopOpen);
                break;
             case  self::Flap_FullOpen:
                $actAction = $this-> SetAction(self::StopFullOpen);
                break;
            case  self::Flap_CloseOpen:
                $this-> SetAction(self::StopClose);
                $actAction = $this-> SetAction(self::Open);
                break;
        }
        $this->SetValue('FlapStatus',$actAction);
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
        //Never delete this line!
        parent::ApplyChanges();

        $this->RegisterVariableIds(self::ReferenciesList);
    }


    private function SwitchOpen () : void
    {
        $this->SetDevice("FlapCloseActorID",false);
        $this->SetDevice("FlapOpenActorID",true);
    }

    private function SwitchClose () : void
    {
        $this->SetDevice("FlapOpenActorID",false);
        $this->SetDevice("FlapCloseActorID",true);
    }

    private function SwitchesStop () : void
    {
        $this->SetDevice("FlapCloseActorID",false);
        $this->SetDevice("FlapOpenActorID",false);
    }

    private function SetAction (int $action) : int
    {
	    $actAction = $action;
        switch ($actAction)
        {
            case  self::Flap_Undef:
                break;
            case self::Flap_StopAll:
                $this->Stoptimer();
                $this->SwitchesStop();
                $actAction = self::Flap_Undef;
                break;
            case self::Flap_CloseOpen:
                $this->SwitchClose ();
                $this->StartClosingTime (); 
                break;
            case self::Flap_Closed:
                break;
            case self::Flap_Close:
                $this->SwitchClose ();
                $this->StartClosingTime (); 
                break;
             case self::Flap_StopClose:
                $this->Stoptimer();
                $this->SwitchesStop();
                $actAction=$this->SetAction(self::Flap_Closed);
                break;
            case  self::Flap_Opened:
                break;
            case self::Flap_Open:
                $this->SwitchOpen ();
                $this->StartOpenTime (); 
                break;
             case self::Flap_StopOpen:
                $this->Stoptimer();
                $this->SwitchesStop();
                $actAction = $this->SetAction(self::Flap_Opened);
                break;
             case self::Flap_StopFullOpen:
                $this->Stoptimer();
                $this->SwitchesStop();
                $actAction = $this->SetAction(self::Flap_FullOpened);
                break;
            case  self::Flap_FullOpened:
                break;
            case self::Flap_FullOpen:
                $this->SwitchOpen ();
                $this->StartFullOpenTime (); 
                break;
        }
        return $actAction;
    }

    private function DoStopAction (int $action) : bool
    {
	    $actAction = $action;
        switch ($actAction)
        {
            case self::Flap_Closed:
            case  self::Flap_Opened:
            case  self::Flap_FullOpened:
                return false;

        }
        return true;
    }

     //Methode setzt Variable, soferne dieser in der Modul-Form aktiviert ist
    public function SetDevice(string $switchName, bool $status)
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        $this->SendDebug(__FUNCTION__, "switchName: ".$switchName, 0);
        $this->SendDebug(__FUNCTION__, "id: ".$id, 0);

        if ($id>1) {
            RequestAction($id, $status);
        }
    }

    private function StartOpenTime () : void
    {
        $this->SetTimerInterval('FLP_FlapTimer',$this->ReadPropertyInteger('OpeningTime')*1000);
    }

    private function StartFullOpenTime () : void
    {
        $this->SetTimerInterval('FLP_FlapTimer', $this->ReadPropertyInteger('MaxOpeningTime')*1000);
    }

    private function StartCloseTime () : void
    {
        $this->SetTimerInterval('FLP_FlapTimer', $this->ReadPropertyInteger('MaxClosingTime')*1000);
    }

    private function Stoptimer ()
    {
        $this->SetTimerInterval('FLP_FlapTimer', 0);
    }

}