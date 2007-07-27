<?php

	$GLOBALS[ '_content_previewer_class_names' ][ basename( __FILE__) ] = 'page_previewer';

	class page_previewer extends default_previewer
	{
		function display_entity() // {{{
		{
			$this->start_table();
			
			// iFrame Preview
			if( !$this->_entity->get_value( 'url' ) )
			{
				// iFrame Preview
				reason_include_once( 'function_libraries/URL_History.php' );
				$url = build_URL( $this->_entity->id() );
				if ($url)
				{
					//$this->show_item_default( 'Public View of Page' , '<iframe src="'.$url.'" width="100%" height="400"></iframe>' );

					// iframe replacement method
					// http://intranation.com/test-cases/object-vs-iframe/
					$this->show_item_default( 'Public View of Page' , '<object classid="clsid:25336920-03F9-11CF-8FD0-00AA00686F13" type="text/html" data="'.$url.'" class="pageViewer"><p><a href="'.$url.'">View page</a></p></object>');
				}
			}
			
			// Everything Else
			$this->show_all_values( $this->_entity->get_values() );
			
			$this->end_table();
		} // }}}
		function show_item_extra_head_content( $field , $value )
		{
			echo '<tr>';
			$this->_row = $this->_row%2;
			$this->_row++;

			echo '<td class="listRow' . $this->_row . ' col1">' . prettify_string( $field );
			if( $field != '&nbsp;' ) echo ':';
			echo '</td>';
			$value = nl2br(htmlspecialchars($value));
			echo '<td class="listRow' . $this->_row . ' col2">' . ( ($value OR (strlen($value) > 0)) ? $value : '<em>(No value)</em>' ). '</td>';

			echo '</tr>';
		}
	}
?>
