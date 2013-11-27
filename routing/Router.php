<?php

require_once(dirname(__FILE__) . "/../logging/Logger.php");
require_once(dirname(__FILE__) . "/../support/web/Requests.php");

class Router {
	
	public $controller_path = "";
	public $fullpath_base = "";
	private $perfmon = NULL;
	private $logger = NULL;
	private $relative_paths = FALSE;
	private $paths = array();
	private $virtual_paths = array();
	
	function __construct($fullpath_base = "", $controller_path = "", $options = array()) {
		$this->fullpath_base = $fullpath_base;
		$this->controller_path = $controller_path;
		if (isset($options["perfmon"]))
			$this->perfmon = $options["perfmon"];
		if (isset($options["logger"]))
			$this->logger = $options["logger"];
		$this->relative_paths = isset($options["relative_paths"]) ? TRUE : FALSE;
	}

	protected function perfmon($enter) {
		global $PERFMON;
		$pf = @$this->perfmon ? $this->perfmon : $PERFMON;
		if (@$pf) {
			if ($enter)
				$pf->enter("router");
			else
				$pf->leave("router");
		}
	}

	protected function log($level, $s) {
		global $LOGGER;
		$lg = @$this->logger ? $this->logger : $LOGGER;
		if (@$lg)
			$lg->message("router", $level, $s);
	}

	private $routes;
	private $metaRoutes;
	private $currentController;
	private $currentAction;
	
	public function addRoute($method, $uri, $controller_action, $options = array()) {
		$entry = array(
			"method" => $method,
			"uri" => $uri,
			"controller_action" => $controller_action,
			"direct" => FALSE,
			"conditions" => array(),
			"arguments" => array()
		);
		if (isset($options["direct"]))
			$entry["direct"] = TRUE;
		if (isset($options["conditions"]))
			$entry["conditions"] = $options["conditions"];
		if (isset($options["path"]))
			$entry["path"] = $options["path"];
		if (isset($options["arguments"]))
			$entry["arguments"] = $options["arguments"];
		$this->routes[] = $entry;
		if (isset($options["path"]))
			$this->paths[$options["path"]] = $entry; 
	}
	
	public function addMetaRoute($key, $controller_action) {
		$this->metaRoutes[$key] = $controller_action;
	}
	
	public function dispatchMetaRoute($key) {
		$this->dispatchControllerAction($this->metaRoutes[$key]); 		
	}
	
	public function dispatchRoute() {
		$this->perfmon(true);
		if (func_num_args() == 2) {
			$method = func_get_arg(0);
			$uri = func_get_arg(1);
		}
		elseif (func_num_args() == 1) {
			$method = "GET";
			$uri = func_get_arg(0);
		}
		else {
			$method = Requests::getMethod();
			$uri = Requests::getPath();
		}
		$this->log(Logger::INFO_2, "Dispatch Route: " . $method . " " . $uri);
		$uri = trim($uri, '/');
		$controller_action = $this->metaRoutes["404"];
		$args = array();
		$direct = FALSE;
		$arguments = array();
		foreach ($this->routes as $route) {
			if ((($route["method"] == "*") || ($route["method"] == $method)) &&
			    (preg_match("/^" . $route["uri"] . "$/", $uri, $matches))) {
			    $conditions = $route["conditions"];
			    $success = true;
				while ($success && $condition = array_shift($conditions))
					$success = $condition();
				if ($success) {
					$controller_action = $route["controller_action"];
					$direct = $route["direct"];
					$arguments = $route["arguments"];
					array_shift($matches);
					$args = $matches;
					break;
				}
			}
		}
		$this->perfmon(false);
		$this->dispatchControllerAction($controller_action, $args, $direct, $arguments);
	}
		
	public function dispatchControllerAction($controller_action, $args = array(), $direct = FALSE, $arguments = array()) {
		$this->perfmon(true);
		$this->log(Logger::INFO_2, "Dispatch Action: " . $controller_action);
		krsort($arguments);
		foreach ($arguments as $key=>$data) {
			$item = @$data["remove"] ? ArrayUtils::removeByIndex($args, $key) : $args[$key];
			if (@$data["write"])
				$data["write"]($item);
		}
		@list($controller_file, $action_function) = explode("#", $controller_action);
		$this->currentController = $controller_file;
		$this->currentAction = $action_function;
		if ($direct) {
			include($controller_file . ".php");
			$this->perfmon(false);
			if ($action_function)
				call_user_func_array($action_function, $args);
		}
		else {
			$cls = $controller_file . "Controller";
			include($this->controller_path . "/" . $cls . ".php");
			$i = strrchr($cls, "/");
			$clsname = $i ? substr($i, 1) : $cls;
			$this->perfmon(false);
			$controller = new $clsname();
			$controller->dispatch($action_function, $args);
		}
	}
	
	public function path($path) {
		$this->perfmon(true);
		if (isset($this->virtual_paths[$path]))
			return $this->virtual_paths[$path]();
		$route = $this->paths[$path];
		$uri = ($this->relative_paths ? "" : "/") . $route["uri"];
		$uri = str_replace('\/', "/", $uri);
		$uri = str_replace('\.', ".", $uri);
		$args = func_get_args();
		array_shift($args);
		$arguments = $route["arguments"];
		ksort($arguments);
		foreach ($arguments as $key=>$data) {
			if (@$data["read"])
				ArrayUtils::insert($args, $key, $data["read"]());
		}
		$in_uri_args = substr_count($uri, "(");
		while (count($args) > 0 && $in_uri_args > 0) {
			$tmp = explode("(", $uri, 2);
			$head = $tmp[0];
			$tmp2 = explode(")", $tmp[1], 2);
			$tail = $tmp2[1];
			$uri = $head . array_shift($args) . $tail;
			$in_uri_args--;
		}
		$params = count($args) > 0 ? $args[0] : array();
		if (($route["method"] != "GET") && ($route["method"] != "POST") && ($route["method"] != "*"))
			$params["_method"] = $route["method"];
		$this->perfmon(false);
		return Requests::buildPath($uri, $params); 
	}
	
	public function fullpath($path) {
		$subpath = call_user_method_array("path", $this, func_get_args());
		return $this->fullpath_base . $subpath;
	}
	
	public function redirect($uri) {
        header("Location: " . $uri);		
	}
		
	public function getCurrentController() {
		return $this->currentController;
	}
	
	public function getCurrentAction() {
		return $this->currentAction;
	}
	
	public function addVirtualPath($path, $function) {
		$this->virtual_paths[$path] = $function;
	}
	
}