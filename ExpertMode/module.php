<?php

declare(strict_types=1);
include_once __DIR__ . '../../libs/RBH_ModuleFunctions.php';

class ExpertMode extends IPSModule
{
    use RBH_ModuleFunctions;

    private const MODULE_PREFIX = 'EXPRT';
    private const MODULE_NAME = 'ExpertMode';

    private const ProfileList =                     'ExpertLevel';
    private const RegisterVariablesUpdateList =     '';
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
        $this->RegisterPropertyString('Password_L2',"");
        $this->RegisterPropertyString('ShowInstanciesL1',"[]");
        $this->RegisterPropertyString('EnableInstanciesL1',"[]");
        $this->RegisterPropertyString('ShowInstanciesL2',"[]");
        $this->RegisterPropertyString('EnableInstanciesL2',"[]");

        $this->RegisterAttributeInteger('ExpertLevel',0);
       
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
        }
        $this->RegisterVariableInteger($variable, $this->Translate('Expert Level'),$profileName, 20);
        $this->EnableAction($variable);
        
        //------------------------------------------------------------------------------------------------------------------
        //Timer ------------------------------------------------------------------------------------------------------------
        $this->RegisterTimer('EXPRT_Timer', 0, 'EXPRT_UpdateTimer($_IPS[\'TARGET\']);');
        //------------------------------------------------------------------------------------------------------------------

        $this->RegisterVariableIds(self::ReferenciesList);
    }


    //Wird aufgerufen bei Änderungen in der GUI, wenn für Variable
    //void EnableAction (string $Ident)
    //regstriert wird
    public function RequestAction($Ident,$Value)
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        switch($Ident) {
              case "Password":
                   $this->SetValue($Ident, $Value);
                   $level = $this->CheckPassword($Value); 
                   $this->WriteAttributeInteger('ExpertLevel',$level);
                   $this->SetLevelProfile($level);
                   break;
              case "ExpertLevel":
                   $this->SetValue($Ident, $Value);
                   $this->OperateExpertSwitch($Value);
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
    }

  
    private function CheckPassword(string $value) : int
    {

        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        $this->SendDebug(__FUNCTION__, 'Password: '.$value, 0);
        $this->SendDebug(__FUNCTION__, 'L1 Password: '.this->ReadPropertyString('Password_L1'), 0);
        $this->SendDebug(__FUNCTION__, 'L2 Password: '.this->ReadPropertyString('Password_L2'), 0);

        $level = 2;
        if (!$this->IsValidStringPair($this->ReadPropertyString('Password_L2'),$value))
        {
             $level = 1;
             if (!$this->IsValidStringPair($this->ReadPropertyString('Password_L1'),$value))
             {
                  $level = 0;
             }
        }
       return $level;
    }

    private function IsValidStringPair(string $value1,string $value2 )
    {
       if (!$this->IsValidString($value1)) return false;
       if (!$this->IsValidString($value2)) return false;
       return $value1==$value2;
    }

    private function IsValidString(string $value)
    {
       if (!is_string($value)) return false;
       if (strlen($value) == 0) return false;
    }

    private function ExpertAction(int $value) : void
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        $expertLevel =  $this->GetValue('ExpertLevel');
       
    }

    private function SetLevelProfile(int $level) : void
    {
        $variable = 'ExpertLevel';
        $profileName =  $this->CreateProfileName($variable);
        if (IPS_VariableProfileExists($profileName)) {
            IPS_SetVariableProfileValues($profileName, 0, $level, 0);
            switch ($level)
            {
                case 0:
                    IPS_SetVariableProfileAssociation($profileName, 0, "Level 0", "", self::Transparent);
                    @IPS_SetVariableProfileAssociation($profileName, 1, "", "", 0);
                    @IPS_SetVariableProfileAssociation($profileName, 2, "", "", 0);
                    break;
                case 1:
                    IPS_SetVariableProfileAssociation($profileName, 0, "Level 0", "", self::Transparent);
                    IPS_SetVariableProfileAssociation($profileName, 1, "Level 1", "", self::Yellow);
                    @IPS_SetVariableProfileAssociation($profileName, 2, "", "", 0);
                    break;
                case 2:
                    IPS_SetVariableProfileAssociation($profileName, 0, "Level 0", "", self::Transparent);
                    IPS_SetVariableProfileAssociation($profileName, 1, "Level 1", "", self::Yellow);
                    IPS_SetVariableProfileAssociation($profileName, 2, "Level 2", "",  self::Red);
                    break;
            }
        }
    }

    private function OperateExpertSwitch(int $level) : void
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        switch ($level)
        {
            case 0:
                $this->StopTimer();
                $this-> OperateHideList ($this->ReadPropertyString('ShowInstanciesL1'),true);
                $this-> OperateHideList ($this->ReadPropertyString('EnableInstanciesL1'),true);
                $this-> OperateHideList ($this->ReadPropertyString('ShowInstanciesL2'),true);
                $this-> OperateHideList ($this->ReadPropertyString('EnableInstanciesL2'),true);
                break;
            case 1:
                $this->StartTimer();
                $this-> OperateHideList ($this->ReadPropertyString('ShowInstanciesL1'),false);
                $this-> OperateHideList ($this->ReadPropertyString('EnableInstanciesL1'),false);
                $this-> OperateHideList ($this->ReadPropertyString('ShowInstanciesL2'),true);
                $this-> OperateHideList ($this->ReadPropertyString('EnableInstanciesL2'),true);
                break;
            case 2:
                $this->StartTimer();
                $this-> OperateHideList ($this->ReadPropertyString('ShowInstanciesL1'),false);
                $this-> OperateHideList ($this->ReadPropertyString('EnableInstanciesL1'),false);
                $this-> OperateHideList ($this->ReadPropertyString('ShowInstanciesL2'),false);
                $this-> OperateHideList ($this->ReadPropertyString('EnableInstanciesL2'),false);
                break;
        }
    }

    private function OperateHideList (string $list, bool $status) : void 
    {
        if (!$this->IsValidStringList($list)) return;
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        $variables = json_decode($list, $status);
        foreach ($variables as $variable) 
        {
                $this->HideItemById($variable['ObjectID'],$status);
        }
    }
    private function OperateLockList (string $list, bool $status) : void 
    {
        if (!$this->IsValidStringList($list)) return;
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        $variables = json_decode($list,$status);
        foreach ($variables as $variable) 
        {
                $this->LockItemById($variable['ObjectID'],$status);
        }
    }

    public function UpdateTimer() : void
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        $this->Stoptimer();
        $this->SetValue('ExpertLevel',0);
        $this->SetLevelProfile(0);
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

        $this->SetLevelProfile($this->GetValue('ExpertLevel'));

    }

    private function StartTimer () : void
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        $this->SetTimerInterval('EXPRT_Timer',$this->ReadPropertyInteger('ExpertLeaseTime')*1000*60);
    }


    private function Stoptimer ()
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        $this->SetTimerInterval('EXPRT_Timer', 0);
    }

}