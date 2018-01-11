<?php
/**
 * @package reason
 * @subpackage minisite_modules
 */

	/**
	 * Include base class & register controller with Reason
	 */
	reason_include_once( 'minisite_templates/modules/form/controllers/default.php' );
	reason_include_once('function_libraries/event_tickets.php');
	include_once(TYR_INC . 'tyr.php');
	$GLOBALS[ '_form_controller_class_names' ][ basename( __FILE__, '.php') ] = 'ThorFormController';

	/**
	 * ThorFormController
	 *
	 * Provides a custom init_admin and init_summary method 
	 *
	 * @todo implement data models in table admin and deprecate me - thor can just use the default controller
	 * @author Nathan White
	 *
	 */
	class ThorFormController extends DefaultFormController
	{
		function delete_row($row_id) {
			$model =& $this->get_model();
			$tc = $model->get_thor_core_object();
			$username = reason_check_authentication();

			$ok_to_delete = false;
			if ($model->user_has_administrative_access()) {
				$ok_to_delete = true; // user is an admin - allow it
			} else {
				$vals = $tc->get_values_for_primary_key($row_id);
				if (@$vals["submitted_by"] == $username) {
					$ok_to_delete = true; // user submitted this row - allow it
				}

			}
				
			if ($ok_to_delete) {
				$tc->delete_by_primary_key($row_id);
				echo "<font color='red'>Row $row_id has been deleted.</font><p>";
			} else {
				trigger_error("user $username tried to delete row $row_id on this form");
			}
		}

		function run() {
			$model = $this->get_model();

			if ($model->user_has_administrative_access() && $model->user_requested_admin()) { // run normal admin mode stuff if it was requested
				parent::run();
			} else if (@$_REQUEST["table_row_action"] == "delete") { // end-user deletion, NOT admin deletion - run some custom code
				if ($model->is_deletable()) {
					$row_id = @$_REQUEST["table_action_id"];
					if (@$_REQUEST["confirm_delete"] == "yes") {
						$this->delete_row($row_id);

						$model =& $this->get_model();
						if ($model->form_allows_multiple()) {
							$tc = $model->get_thor_core_object();
							$user_rows = $tc->get_values_for_user(reason_check_authentication());

							if ($user_rows !== false) {
								$list_link = carl_construct_link();
								echo "<a href='$list_link'>View Your Submission List.</a><br>";
							}
						}
						$create_link = carl_construct_link(Array("form_id" => 0));
						echo "<a href='$create_link'>Create New Form Submission.</a><br>";
					} else {
						$confirm_delete = carl_construct_link(array('confirm_delete' => 'yes'), array('table_row_action', 'table_action_id'));
						$cancel_delete = carl_construct_link();
						echo "Do you really want to delete this entry? This cannot be undone!<P>";

						$tc = $model->get_thor_core_object();
						$data = $tc->get_values_for_primary_key($row_id);
						unset($data["id"]);
						$data = $tc->transform_thor_values_for_display($data);
						if ($data) {
							// we are going to use Tyr to format this up though it is a little silly ...
							$tyr = new Tyr();
							$html = $tyr->make_html_table($data, false);
							echo $html;
						} else {
							echo '<p>No data can be displayed for this row.</p>';
						}

						echo "<a href='$confirm_delete'>Yes, delete this entry.</a><br>";
						echo "<a href='$cancel_delete'>No, leave this entry alone.</a>";
					}
				} else {
					trigger_error("form does not support deletion but flow was attempted.");
				}
			} else { // run default behavior
				parent::run();
			}
		}

		/**
		 * Default admin view gets a thor table admin object and inits it
		 */
		function init_admin()
		{
			$model =& $this->get_model();
			$head_items =& $model->get_head_items();
			$head_items->add_stylesheet(REASON_HTTP_BASE_PATH.'css/forms/form_data.css');
			$head_items->add_stylesheet(REASON_HTTP_BASE_PATH.'css/hide_nav.css');
			$admin =& $model->get_admin_object();
			$admin->init_thor_admin();
		}
		
		function init_form()
		{
			// When using event ticket thor items, the xml is modified at runtime 
			// so we need to make sure the original xml is used to create the populate
			// the database tables. Do that on the first save request, a little earlier
			// than when Thor would do it naturally.
			$model =& $this->get_model();
			if ($model->form_has_event_ticket_elements()) {
				if ($_POST) {
					// This area stashes the full thor xml schema before we adjust
					// it below (to only show one ticket item to a user, etc)
					// The stashed strucuture is only used when the sql table
					// is created for the first time.
					$thor_core = $model->get_thor_core_object();
					$thor_core->stash_current_structure_for_table_generation();
				}
				$this->adjust_event_ticket_node_xml();
			}

			parent::init_form();
		}

		/**
		 * Default summary view gets a table admin object and sets its data
		 */
		function init_summary()
		{
			$model =& $this->get_model();
			$head_items =& $model->get_head_items();
			$head_items->add_stylesheet(REASON_HTTP_BASE_PATH.'css/forms/form_data.css');		
			$summary =& $model->get_summary_object();
			$user_values = $model->get_values_for_user_summary_view();
			$summary->set_data_from_array($user_values);		
		}
		
		function run_form()
		{
			// Forms with Event Tickets need an event ID in the request to 
			// enforce only getting tickets for an event per submission
			if ($this->check_view_and_model_and_invoke_method('should_show_event_tickets_list')) {
				echo $this->check_view_and_invoke_method('get_event_ticket_selector_html');
			} else if ($this->check_view_and_model_and_invoke_method('should_hide_form_when_event_full')) {
				echo $this->check_view_and_invoke_method('get_event_full_html');
			} else if ($this->check_view_and_model_and_invoke_method('should_hide_form_when_event_closed')) {
				echo $this->check_view_and_invoke_method('get_event_closed_html');
			} else {
				parent::run_form();
			}
		}

		function should_show_event_tickets_list()
		{
			$model = $this->get_model();
			if (array_key_exists('event_id', $model->_request_vars)) {
				$eventIdInRequest = $model->_request_vars['event_id'];
			} else {
				$eventIdInRequest = false;
			}

			$eventsOnForm = $this->model->get_events_on_form();

			// Find events related to this form
			$eventReleationshipsExist = count($eventsOnForm) > 0;

			// Make sure the elements are also defined in thor form definition
			$eventsInThorForm = $this->model->get_events_thor_configs();
			$eventFormElementsExist = count($eventsInThorForm) > 0;

			$shouldShowEventList = !$eventIdInRequest &&
					$eventReleationshipsExist && $eventFormElementsExist;


			return $shouldShowEventList;
		}

		function get_event_ticket_selector_html()
		{
			$events = $this->model->get_events_on_form();
			if (count($events) === 1) {
				$redirect = carl_make_redirect(array(
					'event_id' => $events[0]->get_value('id')
				));
				header("Location: $redirect");
				exit;
			}

			$event_info = $this->model->event_tickets_get_all_event_seat_info();

			$html = "<p>Select an event:</p>";
			foreach ($events as $event) {
				$event_id = $event->get_value('id');
				$query_string_args = array(
					'event_id' => $event_id
				);

				$urlForOpenEvents = carl_make_link($query_string_args, '', 'qs_only', true, true);
				$html .= get_ticket_status_html_link($event_id, $event_info[$event_id], $urlForOpenEvents);
			}

			return $html;
		}

		/**
		 * Adjust the original Thor XML in two ways:
		 *   1) Remove event ticket elements that don't match the current request.
		 *      This is so users only see & register for one event at a time.
		 * 
		 *   2) Lookup the real, current number of remaining seats for the event
		 *      displayed and stash that number in the xml. The xml then generates
		 *      a correctly formatted dropdown menu
		 * 
		 * This routine only runs when 'event_id' is present in the request.
		 */
		function adjust_event_ticket_node_xml()
		{
			$model = $this->get_model();
			$currentEventId = 0;
			if (array_key_exists('event_id', $model->_request_vars)) {
				$currentEventId = intval($model->_request_vars['event_id']);
			} else {
				return;
			}

			$thor_core = $model->get_thor_core_object();
			$xmlString = $thor_core->get_thor_xml()->GenerateXML();

			
			$xmlObject = simplexml_load_string($xmlString);
			$xpathSelector = "/form/event_tickets[not(@event_id='$currentEventId')]";
			$inactiveTicketSlots = $xmlObject->xpath($xpathSelector);
			foreach ($inactiveTicketSlots as $node) {
				// The following unset call removes the node from $xmlObject
				unset($node[0]->{0});
			}

			// Add number of remaining seats to the xml
			// to be used by Thor when rendering the form options
			$xpathSelector = "/form/event_tickets";
			$ticketSlots = $xmlObject->xpath($xpathSelector);
			foreach ($ticketSlots as $node) {
				$node['label'] = $model->get_event_ticket_title($currentEventId);
				
				$ticketRequest = $model->event_tickets_get_request();
				$node['remaining_seats'] = $model->event_tickets_get_remaining_seats($ticketRequest['thor_info']);
			}
			$modified_thor_xml = $xmlObject->asXML();
			$thor_core->set_thor_xml($modified_thor_xml);
		}

		function should_hide_form_when_event_full()
		{
			$model =& $this->get_model();
			$formHasTickets = $model->form_has_event_ticket_elements();
			$ticketRequest = $model->event_tickets_get_request();
			$remainingSeatsForCurrentEvent = $model->event_tickets_get_remaining_seats($ticketRequest['thor_info']);
			return $formHasTickets && $remainingSeatsForCurrentEvent < 1;
		}

		function get_event_full_html()
		{
			$model =& $this->get_model();
			$eventTitle = $model->get_event_ticket_title();
			$message = "<h3>Tickets for $eventTitle</h3>";
			$message .= "<p>No tickets are available for this event.</p>";
            $message .= $model->event_tickets_get_request()['thor_info']['sold_out_message'];

			return $message;
		}

		function should_hide_form_when_event_closed()
		{
			$model =& $this->get_model();
			$formHasTickets = $model->form_has_event_ticket_elements();

			$ticketSalesAreClosed = false;
			if ($formHasTickets) {
				$remainingSeatsForCurrentEvent = $model->event_tickets_get_request();

				$thorEventInfo = $remainingSeatsForCurrentEvent['thor_info'];
				$ticketSalesAreClosed = $model->event_tickets_thor_event_is_closed($thorEventInfo);
			}

		return $formHasTickets && $ticketSalesAreClosed;
		}

		function get_event_closed_html()
		{
			$model =& $this->get_model();
			$remainingSeatsForCurrentEvent = $model->event_tickets_get_request();

			$closeTimestamp = $remainingSeatsForCurrentEvent['thor_info']['event_close_datetime'];
			// If no user provided datetime to close ticket sales,
			// use event datetime
			if (!$closeTimestamp) {
				$event = new Entity($remainingSeatsForCurrentEvent['thor_info']['event_id']);
				$closeTimestamp = $event->get_value('datetime');
				$dt = new Datetime($closeTimestamp);
				
				if (defined('REASON_EVENT_TICKETS_DEFAULT_CLOSE_MODIFIER')) {
					$dt->modify(REASON_EVENT_TICKETS_DEFAULT_CLOSE_MODIFIER);
				}
			} else {
				$dt = new Datetime($closeTimestamp);
			}

			$closedMessage = "Registration closed at {$dt->format("g:i a")} on {$dt->format("F jS")}.";
			$closedMessage .= " " . $remainingSeatsForCurrentEvent['thor_info']['cutoff_passed_message'];

			return $closedMessage;
		}

	}
?>
