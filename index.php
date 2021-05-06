<?php 
require 'vendor/autoload.php';

use Carbon\Carbon;
use Carbon\CarbonInterval;

/**
 * calendar class
 */
class Calendar {

    /**
     * @param object the requested schedule to find slots for (the input)
     */
    protected $schedule;    


    /**
     * @param array calendar's existing events (busy slots)
     */
    protected $events = [];


    /**
     * Get available slots based on existing event and the given input
     * 
     * @param array object
     */
    public function getAvailableSlots(object $schedule) 
    {
        return $this->setSchedule($schedule)->setEvents()->searchSlots();
    }


    /**
     * Set the requested schedule from the user's input
     * 
     * @param object schedule 
     * @return self
     */
    private function setSchedule(object $schedule) 
    {
        $this->schedule = $schedule;   
        return $this;
    }


    /**
     * Set the existing events - the busy slots
     * place to handle db query, api service, etc ...
     * 
     * @return self
     */
    private function setEvents() 
    {
        $this->events = json_decode(file_get_contents('calendar/events.json'));
        return $this;
    }

    /**
     * Search available slots 
     * 
     * @return array
     */
    private function searchSlots() 
    {   
        // prep schedule slots bag
        $scheduleSlots = [];

        // initiate relevant carbon instances to handle datetime tasks
        $interval       = CarbonInterval::minutes(15); // set min slots interval, this is just example for 15 min.
        $scheduleStart  = Carbon::instance(new DateTime($this->schedule->startTime)); // schedule start instance
        $scheduleEnd    = Carbon::instance(new DateTime($this->schedule->endTime));  // schedule end instance
        $timeSlots      = new DatePeriod($scheduleStart, $interval, $scheduleEnd); // extract all the time slots in the schedule's period

        foreach($timeSlots as $fromSlot) 
        {
            $toSlot = $fromSlot->copy()->add($interval);
            $scheduleSlots[] = [
                'startTime' => $fromSlot->toDateTimeString(),
                'endTime'   => $toSlot->toDateTimeString(),
                'available' => $this->isAvailable($fromSlot, $toSlot) ? 1 : 0,
                // 'subject'   => $this->schedule->subject, // optional extra subject reference in suggested slot for qa
            ];
        }

        return $scheduleSlots;
    }  
    
    /**
     * Check if slot is available or conflicts with existing events
     * 
     * @return bool
     */
    private function isAvailable($fromSlot, $toSlot) 
    {
        foreach($this->events as $event) 
        {
            foreach($event->meetings as $meeting) 
            {
                // initiate carbon instances for the busy meeting period
                $meetingStart   = Carbon::instance(new DateTime($meeting->startTime));
                $meetingEnd     = Carbon::instance(new DateTime($meeting->endTime));            
                $betweenFrom    = $fromSlot->between($meetingStart, $meetingEnd);  // requested slot conflicts with the begging of the meeting? 
                $betweenTo      = $toSlot->between($meetingStart, $meetingEnd); // requested slot conflicts with the begging of the meeting? 
                
                if($betweenFrom || $betweenTo) 
                {
                    return false;
                }                
            }
        } 
        
        return true;
    }      
}



// this area will catch user's request or any other src with schedule input... 
$input = json_decode(file_get_contents('calendar/input.json'));

// aggregate all the requested schedules with their slots
$cal = new Calendar;
$schedules = [];
foreach($input as $schedule)
{
    $schedule->slots = $cal->getAvailableSlots($schedule);
    $schedules[] = $schedule;
}

// response
header('Content-Type: application/json');
echo json_encode($schedules);
die;  


