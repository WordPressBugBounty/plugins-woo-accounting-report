<?php

namespace BjornTech\Common\ActionScheduler;
use BjornTech\Common\Log\LoggerTrait;

trait ActionSchedulerTrait {
    use LoggerTrait;

    /**
     * Get the amount of items in the queue
     * @return array
     */
    public static function get_processing_queue($id){
        $hook_actions = as_get_scheduled_actions(
            [
                'hook' => $id,
                'status' => ActionScheduler_Store::STATUS_PENDING,
                'claimed' => false,
                'per_page' => -1,
            ],
            'ids'
        );

        $group_actions = as_get_scheduled_actions(
            [
                'group' => $id,
                'status' => ActionScheduler_Store::STATUS_PENDING,
                'claimed' => false,
                'per_page' => -1,
            ],
            'ids'
        );
        return array_unique(array_merge($hook_actions,$group_actions));
    }

    /**
     * Add an action to the queue if it is not already there
     * @param $hook - The id to add to the queue
     * @param $args - The arguments to pass to the action
     * @param $calling_function - The name of the function calling this function
     * @param $entity_type - The type of entity being passed to the queue
     * @param $entity_identifier - The identifier of the entity being passed to the queue
     * 
     * 
     * @return void
     */
    public static function add_to_queue ($hook, $args, $calling_function, $entity_type, $entity_identifier) {
        if (as_has_scheduled_action($hook, $args)) {
            static::log(sprintf('%s (%s): %s already in queue', $calling_function, $entity_identifier, $entity_type));
        } else {
            static::log(sprintf('%s (%s): Queuing %s', $calling_function, $entity_identifier, $entity_type));
            as_schedule_single_action(as_get_datetime_object()->getTimestamp(), $hook, $args);
        }
    }

    /**
     * Remove an action from the queue
     * @param $hook - The id to remove from the queue
     * @param $args - The arguments to pass to the action
     * @param $calling_function - The name of the function calling this function
     * @param $entity_type - The type of entity being passed to the queue
     * @param $entity_identifier - The identifier of the entity being passed to the queue
     * 
     * 
     * @return void
     */
    public static function remove_from_queue ($hook, $args, $calling_function, $entity_type, $entity_identifier) {
        if (as_has_scheduled_action($hook, $args)) {
            static::log(sprintf('%s (%s): Removing %s from queue', $calling_function, $entity_identifier, $entity_type));
            as_unschedule_action($hook, $args);
        } else {
            static::log(sprintf('%s (%s): %s not in queue', $calling_function, $entity_identifier, $entity_type));
        }
    }

    /**
     * Remove all actions from the queue
     * @param $hook - The id to remove from the queue
     * @param $args - The arguments to pass to the action
     * @param $calling_function - The name of the function calling this function
     * @param $entity_type - The type of entity being passed to the queue
     * @param $entity_identifier - The identifier of the entity being passed to the queue
     * 
     * 
     * @return void
     */

    public static function remove_all_from_queue ($hook, $args, $calling_function, $entity_type, $entity_identifier) {
        $actions = static::get_processing_queue($hook);
        if (count($actions) > 0) {
            foreach ($actions as $action) {
                static::log(sprintf('%s (%s): Removing %s from queue', $calling_function, $entity_identifier, $entity_type));
                as_unschedule_action($action);
            }
        } else {
            static::log(sprintf('%s (%s): %s not in queue', $calling_function, $entity_identifier, $entity_type));
        }
    }

    /**
     * Schedule an action to run at a specific time
     * @param $hook - The id to add to the queue
     * @param $args - The arguments to pass to the action
     * @param $timestamp - The timestamp to run the action
     * @param $calling_function - The name of the function calling this function
     * @param $entity_type - The type of entity being passed to the queue
     * @param $entity_identifier - The identifier of the entity being passed to the queue
     * 
     * 
     * @return void
     */

    public static function schedule_action ($hook, $args, $timestamp, $calling_function, $entity_type, $entity_identifier) {
        if (as_has_scheduled_action($hook, $args)) {
            static::log(sprintf('%s (%s): %s already in queue', $calling_function, $entity_identifier, $entity_type));
        } else {
            static::log(sprintf('%s (%s): Scheduling %s', $calling_function, $entity_identifier, $entity_type));
            as_schedule_single_action($timestamp, $hook, $args);
        }
    }

    /**
     * Schedule a recurring action
     * @param $hook - The id to add to the queue
     * @param $args - The arguments to pass to the action
     * @param $timestamp - The timestamp to run the action
     * @param $interval - The interval to run the action
     * @param $calling_function - The name of the function calling this function
     * @param $entity_type - The type of entity being passed to the queue
     * @param $entity_identifier - The identifier of the entity being passed to the queue
     * 
     * 
     * @return void
     */
    public static function schedule_recurring_action ($hook, $args, $timestamp, $interval, $calling_function, $entity_type, $entity_identifier) {
        if (as_has_scheduled_action($hook, $args)) {
            return;
        } else {
            static::log(sprintf('%s (%s): Scheduling %s', $calling_function, $entity_identifier, $entity_type));
            as_schedule_recurring_action($timestamp, $interval, $hook, $args);
        }
    }


}