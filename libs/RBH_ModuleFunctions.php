<?php

/**
 * @project
 * @file          RBH_ModuleFunctions.php
 * @author        Alfred Schorn
 * @copyright     2023 Alfred Schorn
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 */

/** @noinspection PhpUnhandledExceptionInspection */
/** @noinspection PhpUnused */

declare(strict_types=1);

trait RBH_ModuleFunctions
{
    public function RegisterVariableIds(string $itemsString) : void
    {
        foreach (explode(',', $itemsString) as $item) {
            if ($item != "") $this->RegisterPropertyInteger($item, 0);
        }
    }

     private function RegisterLinkIds(string $itemsString) :void
    {
        foreach (explode(',', $itemsString) as $item) {
            $this->RegisterAttributeInteger($item, 0);
        }
    }

    private function CreateLink (int $targetID,string $name,string $iconName, int $position) :int
    {
        $linkID = @IPS_GetLinkIDByName($name, $this->InstanceID);
        if ($targetID != 0 && @IPS_ObjectExists($targetID)) {
            //Check for existing link
            if (!is_int($linkID) && !$linkID) {
                $linkID = IPS_CreateLink();
            }
            else {
	            IPS_DeleteLink($linkID);
                $linkID = IPS_CreateLink();
            }

            IPS_SetParent($linkID, $this->InstanceID);
            IPS_SetPosition($linkID,$position);
            IPS_SetName($linkID, $name);
            IPS_SetIcon($linkID,$iconName);
            IPS_SetLinkTargetID($linkID, $targetID);
            return $linkID;

        } else {
            if (is_int($linkID)) {
                 IPS_DeleteLink($linkID);
            }
        }
         return 0;
    }

    //Methode Registriert Variable für die MessageSink, soferne dieser in der Modul-Form aktiviert ist
    private function RegisterStatusUpdate(string $statusName)
    {
        $id= $this->ReadPropertyInteger($statusName);
        if ($id>0) {
            $this->RegisterMessage($id,VM_UPDATE);
        }
    }

   private function HandleExpertSwitch(int $id, string $hideList, string $lockList )
   {
       $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
       $status = !GetValueBoolean($id);
       if ($id==0)  $status = false;

       if (IsValidStringList($hideList))
       {
           foreach (explode(',', $hideList) as $item)
           {
               $this->HideItem($item,$status);
           }
       }
       if (IsValidStringList($lockList))
       {
           foreach (explode(',',$lockList) as $item)
           {
               $this->LockItem($item,$status);
           }
       }
   }

   private function IsValidStringList(string $list): bool
   {
       if (!is_string($listist)) return false;
       if (strlen($lockList) == 0) return false;
       return true;
   }

   private function ValidateEventPlan(int $weekTimerId): bool
   {
       $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
       $result = false;
       if (($weekTimerId==0) || !@IPS_ObjectExists($weeklySchedule)) return $result;

       $event = IPS_GetEvent($weekTimerId);
       if ($event['EventActive'] == 1) {
           $result = true;
       }

       return $result;
   }

   private function GetWeekTimerAction(int $weekTimerId): int
   {
       $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
       $actionID = 0;
       if (($weekTimerId==0) || !@IPS_ObjectExists($weekTimerId)) return $actionID;

       $event = IPS_GetEvent($weekTimerId);
       if (!$event['EventActive']) return 0;
       $timestamp = time();
       $searchTime = date('H', $timestamp) * 3600 + date('i', $timestamp) * 60 + date('s', $timestamp);
       $weekDay = date('N', $timestamp);
       foreach ($event['ScheduleGroups'] as $group) {
           if (($group['Days'] & pow(2, $weekDay - 1)) > 0) {
               $points = $group['Points'];
               foreach ($points as $point) {
                   $startTime = $point['Start']['Hour'] * 3600 + $point['Start']['Minute'] * 60 + $point['Start']['Second'];
                   if ($startTime <= $searchTime) {
                       $actionID = $point['ActionID'];
                   }
               }
           }
       }

       return $actionID;
   }

   private function DeleteProfileList (string $list) :void
   {

         if (!IsValidStringList($list)) return;
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

    private function GetArrayFromString (string $itemsString)
    {
        return explode(',', $itemsString);
    }

    private function CreateProfileName (string $profileName) : string
    {
         return self::MODULE_PREFIX . '.' . $this->InstanceID . '.' . $profileName;
    }

    private function HideItemById (int $id, bool $hide )
    {
        if ($id==0) return;
        IPS_SetHidden($id,$hide);
    }

    private function LockItem(string $item,bool $status)
    {
        $id = $this->GetIDForIdent($item);
        IPS_SetDisabled($id, $status);
    }
     private function HideItem(string $item,bool $status) :void
    {
        if (empty($item)) return;
        $id = $this->GetIDForIdent($item);
        IPS_SetHidden($id, $status);
    }

    #################### Private

    private function KernelReady(): void
    {
        $this->ApplyChanges();
    }
}