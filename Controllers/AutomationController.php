<?php
/**
 * Automation controller to handle all the operations regarding happy hour, FB live contests
 * funpack and youtube contest.
 */
namespace App\Http\Controllers\Automations;

use App\Dal\Interfaces\IAutomationScheduleRepository;
use App\Http\Controllers\Controller;
use App\Objects\Scheduler\AutomationDataObject;
use App\Objects\Utililties\NotificationSeverity;
use App\Utilities\API\API;
use App\Utilities\API\Tools\RedisManagerAPI;
use App\Utilities\NotificationUtility\NotificationUtility;
use Illuminate\Http\Request;
use App\Objects\Scheduler\ExecutionStatus;
use App\Utilities\SchedulerDispatcherUtility;
use App\ViewModels\Automation;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;

/**
 * Class AutomationController
 *
 * Handles the working of all automation screens
 *
 * @package App\Http\Controllers\Automations
 */
class AutomationController extends Controller
{
    const RUN_TIME = "04:15";
    const RUN_DURATION = 24; //In hours
    const READABLE_RECURRING_TIME = "04:30";
    /***
     * Constant tab type youtube contest
     */
    const YOUTUBE_CONTEST = 'youtube-contest';
    /***
     * @var IAutomationScheduleRepository
     */
    public $_automationSchedulerRepository;
    /***
     * @var RedisManagerAPI
     */
    protected $redisManagerAPI;
    /***
     * AutomationController constructor.
     *
     * @param IAutomationScheduleRepository $automationScheduleRepository
     */
    public function __construct(IAutomationScheduleRepository $automationScheduleRepository)
    {
        $this->_automationSchedulerRepository = $automationScheduleRepository;
        $this->redisManagerAPI = API::getInstance(RedisManagerAPI::class);
    }

    /***
     * Get Schedule compatible date format
     *
     * @param $dateTime
     * @param string $dateTimeFormat
     *
     * @param bool $convertToSteamDBTimeZone
     *
     * @return Carbon
     */
    protected function getScheduleCompatibleDateFormat($dateTime, $dateTimeFormat = "", $convertToSteamDBTimeZone = true)
    {
        $carbonDate = null;

        if(empty($dateTimeFormat)) {
            $dateTimeFormat = FOUNDATION_DATEPICKER_FORMAT." H:i";
        }

        if(!empty($dateTime)) {
            $carbonDate = Carbon::createFromFormat($dateTimeFormat, $dateTime, getAppTimeZone());

            if($convertToSteamDBTimeZone) {
                $carbonDate = $carbonDate->setTimezone(STEAM_DB_TIMEZONE);
            }
        }

        return $carbonDate;
    }

    /***
     * Process Scheduler Tasks and send to dispatcher
     * @throws Exception
     */
    public function processSchedulerTasks()
    {
        try {
            $schedulerDispatcherUtility = new SchedulerDispatcherUtility();
            $executionStatus = [ExecutionStatus::END_PENDING, ExecutionStatus::START_PENDING, ExecutionStatus::RECURRING_PENDING];
            $tasksToProcess = $this->_automationSchedulerRepository->getTasksToProcess($executionStatus);
            //Processing pending tasks (start or end pending)
            if(count($tasksToProcess) > 0) {
                $schedulerDispatcherUtility->dispatchTasks($tasksToProcess);
            }
        } catch (Exception $exception) {
            parent::log($exception, self::class);
        }
    }

	/***
	 * Log scheduler related exceptions
	 *
	 * @param $taskObject
	 * @param $exception
	 *
	 * @throws Exception
	 */
    public function logSchedulerException($taskObject, Exception $exception)
    {
    	$stackTraceAsString = $exception->getTraceAsString();
    	$automationType = makeEnumValuePresentable($taskObject->automationType);

	    $notificationMessage = "There was an exception in automation<br><br><br><b>Automation ID:</b> $taskObject->id<br><br><br><b>Automation Type:</b> $automationType<br><br><br><b>StackTrace:</b> <code_block>$stackTraceAsString</code_block>";

	    NotificationUtility::getInstance()->sendNotification(
		    $notificationMessage,
		    NotificationSeverity::ALERT
	    );

        $this->updateExecutionStatus($taskObject);
        $this->_automationSchedulerRepository->addErrorLog($taskObject->id, $exception);

        parent::log($exception, self::class);
    }

    /***
	 * Log scheduler updates
	 *
	 * @param $updateTask
	 * @param $originalTask
     * @param $message
	 *
	 * @throws Exception
	 */
    public function logScheluderCustomMessages($updateTask, $originalTask, $message)
    {
        $customMessage = implode('\n', $message);
        $content = $updateTask .'\n'. $originalTask .'\n'. $customMessage;
        Log::info($content);
    }

    /***
     * Update execution status
     *
     * @param $taskObject
     * @param bool $isSuccessful
     */
    public function updateExecutionStatus(&$taskObject, $isSuccessful = false) {
        $newExecutionStatus = ExecutionStatus::nextExecutionStatus($taskObject, $isSuccessful);

        $isExecutionStatusUpdated = $this->_automationSchedulerRepository->updateTaskStatus(
        	$taskObject->id,
            $newExecutionStatus
        );

        if($isExecutionStatusUpdated) {
            //Only perform this when the automation ran successfully and next status is recurring pending
            if(ExecutionStatus::RECURRING_PENDING === $newExecutionStatus && $isSuccessful) {
                $isDstOnTomorrow = Carbon::now()->addDay(1)->dst;

                $newTimeInSeconds  = $taskObject->recurringTime;
                $newIsDSTFlagValue = null;

                //If DST is off, add one hour to the recurring time otherwise subtract it.
                if(!$isDstOnTomorrow && $taskObject->isDst === 1) {
                    $newTimeInSeconds += 3600;
                    $newIsDSTFlagValue = 0;
                } else if($isDstOnTomorrow && $taskObject->isDst === 0) {
                    $newTimeInSeconds -= 3600;
                    $newIsDSTFlagValue = 1;
                }

                if($newTimeInSeconds !== $taskObject->recurringTime) {
                    $this->_automationSchedulerRepository->updateRecurringTime(
                        $taskObject->id,
                        $newTimeInSeconds,
                        $newIsDSTFlagValue
                    );
                }
            }

            $taskObject->executionStatus = $newExecutionStatus;
        }
    }

    /***
     * Insert Automation Record wrapper method on repository
     *
     * @param string $controllerNamespace
     * @param Carbon $startDate
     * @param mixed $endDate
     * @param \stdClass $startDataParams
     * @param mixed $endDataParams
     * @param string $automationType
     * @param $description
     * @param int $recurringTime
     * @param int $recurringDuration
     *
     * @return Automation
     */
    public function insertAutomationRecord(
        $controllerNamespace,
        $startDate,
        $endDate = null,
        $startDataParams,
        $endDataParams = null,
        $automationType,
        $description = null,
        $recurringTime = 0,
        $recurringDuration = 0
    ) {
        //Generate start data json
        $startDataJson = $this->generateScheduleDataJson($controllerNamespace, $startDataParams);

        //Generate end data json
        $endDataJson = !is_null($endDataParams) ? $this->generateScheduleDataJson($controllerNamespace, $endDataParams) : null;

        //Set the DST value
        $isDst = null;

        $startDateTimeForDSTCalculation = $startDate->copy();

        $startDateTimeForDSTCalculation->setTimezone(getAppTimeZone());

        //Insert Automation Record
        return $this->_automationSchedulerRepository->insertAutomationRecord(
        	$startDate,
            $endDate,
	        $recurringTime,
	        $startDataJson,
	        $endDataJson,
	        $automationType,
            $description,
            $startDateTimeForDSTCalculation->dst ? 1 : 0,
            $recurringDuration
        );
    }
    /***
     * Update Automation Record wrapper method on repository
     *
     * @param string $controllerNamespace
     * @param int $automationID
     * @param Carbon $startDate
     * @param mixed $endDate
     * @param \stdClass $startDataParams
     * @param mixed $endDataParams
     * @param string $automationType
     * @param $description
     * @param int $recurringTime
     * @param ExecutionStatus $automationStatus
     *
     * @return bool
     */
    protected function updateAutomationRecord(
        $controllerNamespace,
        $automationID,
        $startDate,
        $startDataParams,
        $automationType,
        $endDate = null,
        $endDataParams = null,
        $description = null,
        $recurringTime = 0,
        $automationStatus = null
    ) {

        //Generate start data json
        $startDataJson = $this->generateScheduleDataJson($controllerNamespace, $startDataParams);

        //Generate end data json
        $endDataJson = !is_null($endDataParams) ? $this->generateScheduleDataJson($controllerNamespace, $endDataParams) : null;

        //Insert Automation Record
        return $this->_automationSchedulerRepository->updateAutomationRecord(
            $automationID,
            $startDate,
            $endDate,
            $recurringTime,
            $startDataJson,
            $endDataJson,
            $automationType,
            $description,
            $automationStatus
        );
    }

    /***
     * Generate ScheduleDataJson
     *
     * @param $controllerNamespace
     * @param $params
     * @return string
     */
    protected function generateScheduleDataJson($controllerNamespace, $params)
    {
        $actionMethod = null;
        if(!is_null($params)) {
            $actionMethod = $params->action_method;
            //Unset action method from params
            unset($params->action_method);
        }
        $automationDataObject = new AutomationDataObject();
        return json_encode($automationDataObject->setAutomationDataObject($controllerNamespace, $actionMethod, $params));
    }

    /***
     * Generate filters for automation listing
     *
     * @param $startDate
     * @param $endDate
     * @param $automationType
     * @param $executionStatus
     * @param $search
     * @param $hideInactive
     *
     * @return array
     */
    protected function generateFiltersForAutomationListing(
    	$startDate,
	    $endDate,
	    $automationType,
	    $executionStatus,
        $search = '',
        $hideInactive = ''
    ) {
        $filters = [];

        //It is necessary, so no compromise on it.
        $filters['automation_type'] = $automationType;

        if(!empty($startDate)) {
            $filters['start_date'] = $startDate;
        }

        if(!empty($endDate)) {
            $filters['end_date'] = $endDate;
        }

        if(!empty($executionStatus)) {
            $filters['execution_status'] = $executionStatus;
        }

        if(!empty($search)) {
            $filters['search'] = $search;
        }

        if(!empty($hideInactive)) {
            $filters['hideInactive'] = $hideInactive;
        }

        return $filters;
    }

    /***
     * Enable disable automation
     *
     * @param Request $request
     *
     * @return array
     * @throws Exception
     */
    public function enableDisableAutomation(Request $request)
    {
        $response = [
            'status' => false,
            'message' => 'Failed to enable/disable automation'
        ];
        try {
            $automationId = $request->get('automation_id');
            $status = $request->get('status')  == "true" ? true : false;
            $isSuccessful = $this->_automationSchedulerRepository->toggleAutomationStatusByID($automationId, $status);
            if($isSuccessful) {
                $response['status'] = $isSuccessful;
                $response['message'] = "Status updated successfully!";
            }
        } catch(Exception $exception) {
            parent::log($exception, self::class);
        }
        return $response;
    }

	/**
	 * @param $taskObject
	 * @param string $query
	 * @param integer $numRowsAffected
	 * @param string $message
	 */
    public function sendSQLRanNotification($taskObject, $query, $numRowsAffected, $message = "") {
    	$automationType = makeEnumValuePresentable($taskObject->automationType);

        $notificationSeverity = NotificationSeverity::SUCCESS;
	    
	    if(empty($message)) {
		    $message = "<b>Automation Type: $automationType ran</b><br><br><br>Following queries affected <b>$numRowsAffected</b> rows.<br><br><br><b>Queries:</b> <code_block>$query</code_block>";
	    }

	    NotificationUtility::getInstance()->sendNotification(
		    $message,
		    $notificationSeverity
	    );
    }
    /***
     * Purge redis keys
     *
     * @param $redisKeys
     * @return string $redisKeysRemoveMessage
     */
    public function purgeRedisKeys($redisKeys)
    {

        $redisKeysRemoveMessage = null;
        if(count($redisKeys) > 0) {
            $isSuccessful = $this->redisManagerAPI->deleteRedisKeys($redisKeys);
            if($isSuccessful) {
                $isSuccessMessage = "Following redis keys are purged.";
            } else {
                $isSuccessMessage = "Following redis keys not purged please check.";
            }
            $redisKeys = implode("<br>", $redisKeys);
            $redisKeysRemoveMessage = "<br><br>$isSuccessMessage <br><br><br><b>Redis Keys:</b><br><br> <code_block>$redisKeys</code_block>";
        }
        return $redisKeysRemoveMessage;
    }

    /**
     * Given the record id return automation record for editing.
     *
     * @param $recordId
     * @param $getEloquentObject
     *
     * @return Automation|null
     * @throws Exception
     */
    public function getAutomationRecordForEdit($recordId = null, $getEloquentObject = true) {
        $record = null;

        try {
            if(is_null($recordId)) {
                $recordId = \request()->get('automation_id');
            }

            if(!is_null($recordId)) {
                $record = $this->_automationSchedulerRepository->fetchRecordByID($recordId, true, $getEloquentObject);
            }
        } catch (Exception $exception) {
            $this->log($exception, __CLASS__);
        }

        return $record;
    }

    /**
     * Take readable recurring time, convert it into utc and send time in seconds back.
     * If start date time is provided get recurring time based on hours and minutes of that timestamp
     *
     * NOTE: Please make sure $carbonDateTime is in UTC or STEAM_DB_TIMEZONE
     *
     * @param Carbon $carbonDateTime
     *
     * @return int
     */
    public function getRecurringTimeInSecondsInUTC($carbonDateTime = null) {
        if(empty($carbonDateTime)) {
            $carbonDateTime = Carbon::createFromFormat("H:i", self::READABLE_RECURRING_TIME, getAppTimeZone())
                                ->setTimezone(STEAM_DB_TIMEZONE);
        }

        $hours = $carbonDateTime->hour;
        $minutes = $carbonDateTime->minute;

        return getSecondsFromTime($hours, $minutes);
    }
}