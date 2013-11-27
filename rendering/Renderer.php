<?php

require_once(dirname(__FILE__) . "/../logging/Logger.php");

class Renderer {
	
	protected static function log($level, $s) {
		global $LOGGER;
		if (@$LOGGER)
			$LOGGER->message("framework.renderer", $level, $s);
	}
	
	protected static function perfmon($enter) {
		global $PERFMON;
		if (@$PERFMON) {
			if ($enter)
				$PERFMON->enter("renderer");
			else
				$PERFMON->leave("renderer");
		}
	}

	private $views_directory;
	private $layouts_directory;
	
	private $default_layout;
	private $render_once = array();
	
	public function __construct($views_directory, $layouts_directory = NULL, $default_layout = "application") {
		$this->default_layout = $default_layout;
		$this->views_directory = $views_directory;
		$this->layouts_directory = $layouts_directory;
	}
	
	public function render($options, $locals = array()) {
		static::perfmon(true);
		if (!is_array($options))
			$options = array("template" => $options);
		if (isset($options["once"]) && $options["once"]) {
			if (isset($this->render_once[$options["template"]])) {
				static::perfmon(false);
				return;
			}
			$this->render_once[$options["template"]] = TRUE;
		}
		$template = $options["template"];
		static::log(Logger::INFO_3, "Rendering '{$template}'.");
		$layout = isset($options["nolayout"]) || !isset($this->layouts_directory) ? NULL : (isset($options["layout"]) ? $options["layout"] : $this->default_layout);
		$yield = $this->views_directory . "/" . $template . ".php";
		foreach ($locals as $key => $value ) {
			$$key = $value;
		}
		if ($layout)
			include($this->layouts_directory . "/" . $layout . ".php");
		else
			include($yield);		
		static::perfmon(false);
	}
	
	public function render_to_string($options, $locals = array()) {
		static::perfmon(true);
		ob_start();
		$this->render($options, $locals);
		$str = ob_get_contents();
		ob_end_clean();
		static::perfmon(false);
		return $str;
	}
	
	public function render_partial($options, $locals = array()) {
		static::perfmon(true);
		if (!is_array($options))
			$options = array("template" => $options);
		$options["nolayout"] = true;
		$this->render($options, $locals);
		static::perfmon(false);
	}
	
	public function render_partial_to_string($options, $locals = array()) {
		static::perfmon(true);
		ob_start();
		$this->render_partial($options, $locals);
		$str = ob_get_contents();
		ob_end_clean();
		static::perfmon(false);
		return $str;
	}

}