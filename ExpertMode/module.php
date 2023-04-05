<?php

declare(strict_types=1);
include_once __DIR__ . '../../libs/RBH_ModuleFunctions.php';

class ExpertMode extends IPSModule
{
    use RBH_ModuleFunctions;

    private const MODULE_PREFIX = 'EXPRT';
    private const MODULE_NAME = 'ExpertMode';

    private const ProfileList =                     'ExpertMode,ExpertLevel';
    private const RegisterVariablesUpdateList =     'ExpertLevel';
    private const RegisterReferenciesUpdateList =   '';
    private const ReferenciesList =                 '';
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

        $this->RegisterPropertyInteger('ExpertLeaseTime',60);
        $this->RegisterPropertyString('Password_L1',"");
        $this->RegisterPropertyString('ShowInstanciesL1',"[]");
        $this->RegisterPropertyString('EnableInstanciesL1',"[]");
        $this->RegisterPropertyString('ShowInstanciesL2',"[]");
        $this->RegisterPropertyString('EnableInstanciesL2',"[]");
       
        $this->DeleteProfileList (self::ProfileList);

       //Variablen --------------------------------------------------------------------------------------------------------
       //FlapAction
        $variable = 'Password';
        $this->RegisterVariableString($variable, $this->Translate('Password'),"", 10);
        $this->EnableAction($variable);

        $variable = 'ExpertLevel';
        $profileName =  $this->CreateProfileName($variable);
        if (!IPS_VariableProfileExists($profileName)) {
            IPS_CreateVariableProfile($profileName, 1);
            IPS_SetVariableProfileValues($profileName, 0, 2, 0);
            IPS_SetVariableProfileIcon($profileName, "Shutter");
            IPS_SetVariableProfileAssociation($profileName, 0, "Level 0", "", self::Transparent);
            IPS_SetVariableProfileAssociation($profileName, 1, "Level 1", "", self::Transparent);
            IPS_SetVariableProfileAssociation($profileName, 2, "Level 2", "",  self::Transparent);
        }
        $this->RegisterVariableInteger($variable, $this->Translate('Expert Level'),$profileName, 20);
        //$this->EnableAction($variable);

        $variable = 'ExpertMode';
        $profileName = $this->CreateProfileName($variable);
        if (!IPS_VariableProfileExists($profileName)) {
            IPS_CreateVariableProfile($profileName, 0);
            IPS_SetVariableProfileIcon($profileName, "Ok");
            IPS_SetVariableProfileAssociation($profileName, false, "Aus", "", self::Transparent);
            IPS_SetVariableProfileAssociation($profileName, true, "Ein", "", self::Green);
        }
        $this->RegisterVariableBoolean($variable, $this->Translate('Expert Mode'), $profileName, 20);
        $this->EnableAction($variable);

        
        //------------------------------------------------------------------------------------------------------------------
        //Timer ------------------------------------------------------------------------------------------------------------
        $this->RegisterTimer('EXPRT_FlapTimer', 0, 'EXPRT_UpdateTimer($_IPS[\'TARGET\']);');
        //------------------------------------------------------------------------------------------------------------------

        $this->RegisterVariableIds(self::ReferenciesList);
    }


    //Wird aufgerufen bei Änderungen in der GUI, wenn für Variable
    //void EnableAction (string $Ident)
    //regstriert wird
    public function RequestAction($Ident,$Value)
    {
        switch($Ident) {
              case "PasswordL1":
                   $this->SetValue($Ident, $Value);
                   break;
              case "PasswordL2":
                   $this->SetValue($Ident, $Value);
                   break;
              case "ExpertMode":
                   if (!$Value) $this->SetValue($Ident, $Value);
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

         /*
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
        */
    }

    /*
    private function SelectFlapAction(int $sender) : bool
    {
        
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        $id = $this->GetIDForIdent('FlapAction');
        if (!$this->IsValidId($id)) return false;
        if ($id != $sender) return false;
        return true;
    }

    private function SelectExpertSwitch(int $sender) : bool
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        $id = $this->ReadPropertyInteger('ExpertModeID');
        if (!$this->IsValidId($id)) return false;
        if ($id != $sender) return false;
        return true;
    }
    */

    private function ExpertAction(int $value) : void
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        $expertLevel =  $this->GetValue('ExpertMode');
        /*
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
                else 
                {
	                $this->SetValue('FlapAction',self::Stop);
                }
                break;
            case self::FullOpen:
                if ($flapStatus != self::Flap_FullOpened)
                {
                    $actAction = $this-> SetAction(self::Flap_FullOpen);
                }
                else 
                {
	                $this->SetValue('FlapAction',self::Stop);
                }
                break;
            case self::AutoOpen:
                if ($flapStatus != self::Flap_Opened)
                {
                    $actAction = $this-> SetAction(self::Flap_CloseOpen);
                }
                else 
                {
	                $this->SetValue('FlapAction',self::Stop);
                }
                break;
        }
        $this->SetValue('FlapStatus',$actAction);
        */
    }

    private function OperateExpertSwitch(int $id) : void
    {
        $this->HandleExpertSwitch($id,self::ExpertHideList,self::ExpertLockList);
    }

    public function UpdateTimer() : void
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        /*
        $action = $this->GetValue('FlapStatus');
        $actAction = $action;
        switch ($action)
        {
            case  self::Flap_Close:
                $actAction = $this-> SetAction(self::Flap_StopClose);
                $this->SetValue('FlapAction',self::Stop);
                break;
            case  self::Flap_Open:
                $actAction = $this-> SetAction(self::Flap_StopOpen);
                $this->SetValue('FlapAction',self::Stop);
                break;
             case  self::Flap_FullOpen:
                $actAction = $this-> SetAction(self::Flap_StopFullOpen);
                $this->SetValue('FlapAction',self::Stop);
                break;
            case  self::Flap_CloseOpen:
                $this-> SetAction(self::Flap_StopClose);
                $actAction = $this-> SetAction(self::Flap_Open);
                break;
        }
        $this->SetValue('FlapStatus',$actAction);
        */
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

        //Alle benötigten aktiven Referenzen für die Messagesink anmelden
        $this->RegisterPropertiesUpdateList(self::RegisterReferenciesUpdateList);
        $this->RegisterVariablesUpdateList(self::RegisterVariablesUpdateList);

    }

    private function StartTimer () : void
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        $this->SetTimerInterval('EXPRT_Timer',$this->ReadPropertyInteger('ActiveTime')*1000);
    }


    private function Stoptimer ()
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        $this->SetTimerInterval('EXPRT_Timer', 0);
    }

}