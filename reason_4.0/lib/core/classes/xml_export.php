<?php
/**
 * XML export
 * @package reason
 * @subpackage classes
 */

 include_once('reason_header.php');

/**
 * Class for handling standard Reason exports
 *
 * Takes an array of entities and generates an xml description of the entities
 *
 * THIS IS STILL EXPERIMENTAL CODE. Don't expect the XML from this module to be in exactly
 * this form for now.
 *
 * @author Matt Ryan
 *
 * Sample code:
 * $es = new entity_selector();
 * // ... some rules go in here ...
 * $entities = $es->run_one();
 * $export = new reason_xml_export();
 * $xml = $export->get_xml($entities);
 */

class reason_xml_export
{
	/** array of versions supported by the class
	 * @var array keys=version name, values=class method to run for this version
	 */
	var $versions = array('0.1'=>'get_xml_version_point_one');

	/** the default version used if no version is provided to this class
	 * This should be one of the keys in the $versions class variable
	 * @var string
	 */
	var $default_version = '0.1';

	/** Method for finding out which versions are supported by the class
	 * @return array
	 */
	function versions_supported()
	{
		return array_keys($this->versions);
	}

	/** Method for finding out which version is the current default
	 * @return string
	 */
	function get_default_version()
	{
		return $this->default_version;
	}

	/** Determine is a particular version is supported by this class
	 * @return bool
	 */
	function version_is_supported($version)
	{
		if(empty($this->versions[$version]))
		{
			return false;
		}
		else
		{
			return true;
		}
	}

	/** Determine what function should be used for a particular version
	 * @return string (NULL returned if none available)
	 */
	function function_name_for_version($version)
	{
		if(!empty($this->versions[$version]))
		{
			return $this->versions[$version];
		}
		else
		{
			return NULL;
		}
	}

	/** The main public function for this class
	 * Mostly other classes should just use this function
	 * It is up to the controlling class to serve contents or to store in file
	 * It is also up to the controlling class to determine exactly which entities are to be included
	 * @return string contents of XML file
	 */
	function get_xml($entities, $version = '')
	{
		if(empty($version))
		{
			$version = $this->get_default_version();
		}
		if($this->version_is_supported($version))
		{
			$function = $this->function_name_for_version($version);
			if(method_exists($this, $function))
			{
				return $this->$function($entities);
			}
			else
			{
				trigger_error('XML version '.$version.' wants to run a method called "'.$function.'" on the xml export class, but no method with that name is available.');
			}
		}
		else
		{
			trigger_error('Unsupported xml version requested: '.$version);
		}
	}

	/** Generates reason data version 0.1
	 * This is a very rough implementation of the Reason XML exporting scheme
	 * It's probably best to use it only if there is nothing better available
	 * @return string contents of XML file
	 */
	function get_xml_version_point_one($entities)
	{
		$gen = new reason_xml_export_generator_version_point_one();
		return $gen->get_xml($entities);
	}

}

/**
 * An abstract class that defines the API of an XML export generator
 */
class reason_xml_export_generator
{
	/**
	 * Get an XML representation for a set of Reason entities
	 * @param array $entities
	 * @return string (XML)
	 */
	function get_xml($entities)
	{
		trigger_error('This method must be overloaded');
		return '';
	}
}

class ExportXMLWriter extends XMLWriter
{
	public function startElementWithAttrs($name, $attributes)
	{
		$this->startElement($name);
		foreach ($attributes as $k => $v) {
			$this->addAttribute($k, $v);
		}
	}

	public function addAttribute($key, $val)
	{
		$this->startAttribute($key);
		$this->text($val);
		$this->endAttribute();
	}

	public function addTextSafe($v)
	{
		if ($v) {
			if (is_numeric($v)) {
				$this->text($v);
			} else {
				$entitied = htmlentities($v, ENT_QUOTES, 'utf-8');
				$this->writeCdata($entitied);
			}
		}
	}

	public function addCdataText($value) {
		$this->writeCdata($value);
	}

	public function formatAndFinish($site, $type)
	{
		$this->endDocument();
		$string = $this->outputMemory();

		$dom = new DOMDocument('1.0', 'UTF-8');
		$dom->preserveWhiteSpace = false;
		$dom->formatOutput = true;

		if ($dom->loadXML($string)) {
			return $dom->saveXML();
		} else {
			throw new Exception("Couldn not format XML for type '{$type}' in site '{$site}'");
		}
	}
}

/**
 * A class that generates the 0.1 version of the Reason XML export data format
 */
class reason_xml_export_generator_version_point_one extends reason_xml_export_generator
{
	/**
	 * Get an XML representation for a set of Reason entities
	 * @access public
	 * @param array $entities
	 * @return string (XML)
	 */
	function get_xml($entities)
	{
		$xml = new ExportXMLWriter();
		$xml->openMemory();
		$xml->startDocument("1.0", 'utf-8');

		$xml->startElementWithAttrs('reason_data', [
			'version' => '0.1',
			'from' => 'http://' . REASON_HOST . '/',
		]);

		foreach ($entities as $e) {
			$type = new entity($e->get_value('type'));
			$site = $e->get_owner();

			$xml->startElementWithAttrs('entity', [
				'id' => $e->id(),
				'type' => $type->get_value('unique_name'),
				'site' => $site->get_value('unique_name'),
			]);
			if ($e->get_value('unique_name')) {
				$xml->addAttribute('unique_name', $e->get_value('unique_name'));
			}

			foreach ($e->get_values() as $k => $v) {
				$xml->startElementWithAttrs('value', [
					'name' => $k,
				]);
				$xml->addTextSafe($v);
				$xml->endElement();

				if (in_array($k, ['created_by', 'last_edited_by'])) {
					$username = $this->get_reason_username($v);
					$xml->startElementWithAttrs('value', [
						'name' => $k . '_username',
					]);
					$xml->addTextSafe($username);
					$xml->endElement();
				}
			}

			if ($e->method_supported('get_export_generated_data')) {
				$data = $e->get_export_generated_data();
				if (!empty($data)) {
					foreach ($data as $k => $v) {
						$xml->startElementWithAttrs('value', [
							'name' => '_generated_' . $k,
							'type' => 'computed',
						]);
						$xml->addTextSafe($v);
						$xml->endElement();
					}
				}
			}

			$xml->startElement('relationships');

			$left_rel_info = $e->get_left_relationships_info();
			foreach ($e->get_left_relationships() as $alrel_id => $rels) {
				if (is_numeric($alrel_id) && !empty($rels)) {
					$this->_get_rel_xml_lines($xml, $rels, $left_rel_info[$alrel_id], $alrel_id, 'left');
				}
			}

			$right_rel_info = $e->get_right_relationships_info();
			foreach ($e->get_right_relationships() as $alrel_id => $rels) {
				if (is_numeric($alrel_id) && !empty($rels)) {
					$this->_get_rel_xml_lines($xml, $rels, $right_rel_info[$alrel_id], $alrel_id, 'right');
				}
			}

			$xml->endElement(); //relationships
			$xml->endElement(); //entity
		}
		$xml->endElement(); //reason_data

		return $xml->formatAndFinish($site->get_value('unique_name'), $type->get_value('unique_name'));
	}

	/**
	 * @access private
	 * @param ExportXMLWriter $xw
	 * @param array $rels
	 * @param array $rels_info
	 * @param integer $alrel_id
	 * @param string $dir
	 */
	function _get_rel_xml_lines(ExportXMLWriter &$xw, $rels, $rels_info, $alrel_id, $dir)
	{
		$xw->startElementWithAttrs('alrel', [
			'name' => relationship_name_of($alrel_id),
			'id' => $alrel_id,
			'dir' => $dir
		]);

		foreach ($rels as $position => $rel) {

			$xw->startElementWithAttrs('rel', [
				'to_entity_id' => $rel->id(),
				'to_uname' => ($rel->get_value('unique_name') ? htmlspecialchars($rel->get_value('unique_name')) : ''),
			]);

			if (isset($rels_info[$position])) {
				foreach ($rels_info[$position] as $key => $val) {
					if ($key != 'type' && $key != 'entity_a' && $key != 'entity_b') {
						$xw->startElementWithAttrs('attr', [
							'name' => $key
						]);
						$xw->addTextSafe($val);
						$xw->endElement(); // attr
					}
				}
			}
			$xw->endElement(); // rel
		}
		$xw->endElement(); // alrel
	}

	function get_reason_username( $reason_id ) {
		static $user_netids;
		if ( empty( $reason_id ) ) {
			return null;
		}
		if ( ! isset( $user_netids[ $reason_id ] ) ) {
			$es = new entity_selector();
			$es->limit_tables( 'entity' );
			$es->limit_fields( 'name' );
			$es->add_type( id_of( 'user' ) );
			$es->add_relation( 'entity.id = ' . reason_sql_string_escape( $reason_id ) );
			$es->set_num( 1 );
			$result = $es->run_one();
			if ( $result && $result[ $reason_id ]->get_value( 'name' ) ) {
				$user_netids[ $reason_id ] = (string) $result[ $reason_id ]->get_value( 'name' );
			} else {
				$user_netids[ $reason_id ] = null;
			}
		}

		return $user_netids[ $reason_id ];
	}
}
?>
