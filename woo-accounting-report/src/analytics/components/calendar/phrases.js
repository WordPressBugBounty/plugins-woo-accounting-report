/**
 * External dependencies
 */
import { __, sprintf } from '@wordpress/i18n';

export default {
	calendarLabel: __( 'Calendar', 'woo-accounting-report' ),
	closeDatePicker: __( 'Close', 'woo-accounting-report' ),
	focusStartDate: __(
		'Interact with the calendar and select start and end dates.',
		'woo-accounting-report'
	),
	clearDate: __( 'Clear Date', 'woo-accounting-report' ),
	clearDates: __( 'Clear Dates', 'woo-accounting-report' ),
	jumpToPrevMonth: __(
		'Move backward to switch to the previous month.',
		'woo-accounting-report'
	),
	jumpToNextMonth: __(
		'Move forward to switch to the next month.',
		'woo-accounting-report'
	),
	enterKey: __( 'Enter key', 'woo-accounting-report' ),
	leftArrowRightArrow: __( 'Right and left arrow keys', 'woo-accounting-report' ),
	upArrowDownArrow: __( 'up and down arrow keys', 'woo-accounting-report' ),
	pageUpPageDown: __( 'page up and page down keys', 'woo-accounting-report' ),
	homeEnd: __( 'Home and end keys', 'woo-accounting-report' ),
	escape: __( 'Escape key', 'woo-accounting-report' ),
	questionMark: __( 'Question mark', 'woo-accounting-report' ),
	selectFocusedDate: __( 'Select the date in focus.', 'woo-accounting-report' ),
	moveFocusByOneDay: __(
		'Move backward (left) and forward (right) by one day.',
		'woo-accounting-report'
	),
	moveFocusByOneWeek: __(
		'Move backward (up) and forward (down) by one week.',
		'woo-accounting-report'
	),
	moveFocusByOneMonth: __( 'Switch months.', 'woo-accounting-report' ),
	moveFocustoStartAndEndOfWeek: __(
		'Go to the first or last day of a week.',
		'woo-accounting-report'
	),
	returnFocusToInput: __( 'Return to the date input field.', 'woo-accounting-report' ),
	keyboardNavigationInstructions: __(
		'Press the down arrow key to interact with the calendar and select a date.',
		'woo-accounting-report'
	),
	chooseAvailableStartDate: ( { date } ) =>
		/* translators: %s: start date */
		sprintf( __( 'Select %s as a start date.', 'woo-accounting-report' ), date ),
	chooseAvailableEndDate: ( { date } ) =>
		/* translators: %s: end date */
		sprintf( __( 'Select %s as an end date.', 'woo-accounting-report' ), date ),
	chooseAvailableDate: ( { date } ) => date,
	dateIsUnavailable: ( { date } ) =>
		/* translators: %s: unavailable date which was selected */
		sprintf( __( '%s is not selectable.', 'woo-accounting-report' ), date ),
	dateIsSelected: ( { date } ) =>
		/* translators: %s: selected date successfully */
		sprintf( __( 'Selected. %s', 'woo-accounting-report' ), date ),
};
