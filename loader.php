<?php

final class Loader
{
	/**
	 * Can be used to prevent loading all modules except main and fileman
	 */
	const SAFE_MODE = false;

	const BITRIX_HOLDER = "amr";
	const LOCAL_HOLDER = "local";

	private static $safeModeModules = array("main", "fileman");

	private static $arLoadedModules = array("main" => true);
	private static $arSemiloadedModules = array();
	private static $arLoadedModulesHolders = array("main" => self::BITRIX_HOLDER);
	private static $arSharewareModules = array();

	/**
	 * Custom autoload paths.
	 * @var array [namespace => path]
	 */
	private static $customNamespaces = [];

	/**
	 * Returned by includeSharewareModule() if module is not found
	 */
	const MODULE_NOT_FOUND = 0;
	/**
	 * Returned by includeSharewareModule() if module is installed
	 */
	const MODULE_INSTALLED = 1;
	/**
	 * Returned by includeSharewareModule() if module works in demo mode
	 */
	const MODULE_DEMO = 2;
	/**
	 * Returned by includeSharewareModule() if the trial period is expired
	 */
	const MODULE_DEMO_EXPIRED = 3;

	private static $arAutoLoadClasses = array();

	private static $isAutoLoadOn = true;

	const ALPHA_LOWER = "qwertyuioplkjhgfdsazxcvbnm";
	const ALPHA_UPPER = "QWERTYUIOPLKJHGFDSAZXCVBNM";

	/**
	 * Includes module by its name
	 *
	 * @param string $moduleName Name of the included module
	 * @return bool Returns true if module was included successfully, otherwise returns false
	 * @throws LoaderException
	 */
	public static function includeModule($moduleName)
	{
		// echo "$moduleName";die(' 59');
		// var_dump(preg_match("#[^a-zA-Z0-9._]#", $moduleName));die;

		if (!is_string($moduleName) || $moduleName == "")
			throw new LoaderException("Empty module name");
		if (preg_match("#[^a-zA-Z0-9._]#", $moduleName))
			throw new LoaderException(sprintf("Module name '%s' is not correct", $moduleName));

		$moduleName = strtr($moduleName, static::ALPHA_UPPER, static::ALPHA_LOWER);
		// var_dump($moduleName);die;
		// var_dump((self::SAFE_MODE));die;	//false

		if (self::SAFE_MODE)
		{
			if (!in_array($moduleName, self::$safeModeModules))
				return false;
		}

		// var_dump(((self::$arLoadedModules)));die(' 77');

		if (isset(self::$arLoadedModules[$moduleName]))
			return self::$arLoadedModules[$moduleName];

		if (isset(self::$arSemiloadedModules[$moduleName]))
			trigger_error("Module '".$moduleName."' is in loading progress", E_USER_WARNING);

		// $arInstalledModules = ModuleManager::getInstalledModules();
		// if (!isset($arInstalledModules[$moduleName])) {
		// 	// echo "return?";
		// 	return self::$arLoadedModules[$moduleName] = false;
		// }

		$documentRoot = static::getDocumentRoot();

		// var_dump($documentRoot);die(' 94');

		$moduleHolder = self::LOCAL_HOLDER;
		$pathToInclude = $documentRoot."/".$moduleHolder."/modules/".$moduleName."/include.php";

		// var_dump($pathToInclude);die(' 98');
		// var_dump((file_exists($pathToInclude)));die(' 99');


		if (!file_exists($pathToInclude))
		{
			$moduleHolder = self::BITRIX_HOLDER;
			$pathToInclude = $documentRoot."/".$moduleHolder."/modules/".$moduleName."/include.php";
			// var_dump($pathToInclude);die(' 106');


			if (!file_exists($pathToInclude))
				return self::$arLoadedModules[$moduleName] = false;
		}

		self::$arLoadedModulesHolders[$moduleName] = $moduleHolder;
		self::$arSemiloadedModules[$moduleName] = true;

		// var_dump(self::$arLoadedModulesHolders);die(' 113');
		// var_dump(self::$arSemiloadedModules);die(' 114');

		var_dump($pathToInclude);
		echo "<br>";

		$res = self::includeModuleInternal($pathToInclude);



		// var_dump($res);//die(' res =');
		// var_dump(isset($var_A));


		// var_dump(self::$arSemiloadedModules);//die(' 124');
		unset(self::$arSemiloadedModules[$moduleName]);
		// var_dump(self::$arSemiloadedModules);die(' 126');


		if ($res === false)
			return self::$arLoadedModules[$moduleName] = false;


		self::$arLoadedModules[$moduleName] = true;
		// var_dump($moduleName);die(' 133');
		// var_dump(self::$arLoadedModules[$moduleName] = true);die(' 134');	//true
		// var_dump(self::$arLoadedModules);die(' 135');	//true

		return self::$arLoadedModules[$moduleName];
	}

	private static function includeModuleInternal($path)
	{
		/** @noinspection PhpUnusedLocalVariableInspection */
		global $DB, $MESS;
		return include_once($path);
	}

	/**
	 * Includes shareware module by its name.
	 * Module must initialize constant <module name>_DEMO = Y in include.php to define demo mode.
	 * include.php must return false to define trial period expiration.
	 * Constants is used because it is easy to obfuscate them.
	 *
	 * @param string $moduleName Name of the included module
	 * @return int One of the following constant: Loader::MODULE_NOT_FOUND, Loader::MODULE_INSTALLED, Loader::MODULE_DEMO, Loader::MODULE_DEMO_EXPIRED
	 */
	public static function includeSharewareModule($moduleName)
	{
		if (isset(self::$arSharewareModules[$moduleName]))
			return self::$arSharewareModules[$moduleName];

		$moduleNameTmp = str_replace(".", "_", $moduleName);

		if (self::includeModule($moduleName))
		{
			if (defined($moduleNameTmp."_DEMO") && constant($moduleNameTmp."_DEMO") == "Y")
				self::$arSharewareModules[$moduleName] = self::MODULE_DEMO;
			else
				self::$arSharewareModules[$moduleName] = self::MODULE_INSTALLED;

			return self::$arSharewareModules[$moduleName];
		}

		if (defined($moduleNameTmp."_DEMO") && constant($moduleNameTmp."_DEMO") == "Y")
			return self::$arSharewareModules[$moduleName] = self::MODULE_DEMO_EXPIRED;

		return self::$arSharewareModules[$moduleName] = self::MODULE_NOT_FOUND;
	}

	public static function clearModuleCache($moduleName)
	{
		if (!is_string($moduleName) || $moduleName == "")
			throw new LoaderException("Empty module name");

		if($moduleName !== "main")
		{
			unset(static::$arLoadedModules[$moduleName]);
			unset(static::$arLoadedModulesHolders[$moduleName]);
		}

		if (isset(static::$arSharewareModules[$moduleName]))
			unset(static::$arSharewareModules[$moduleName]);
	}

	/**
	 * Returns document root
	 *
	 * @return string Document root
	 */
	public static function getDocumentRoot()
	{
		static $documentRoot = null;
		if ($documentRoot === null)
			$documentRoot = rtrim($_SERVER["DOCUMENT_ROOT"], "/\\");

        // echo "string = $documentRoot";die;
		return $documentRoot;
	}

	public static function switchAutoLoad($value = true)
	{
		static::$isAutoLoadOn = $value;
	}

	/**
	 * Registers classes for auto loading.
	 * All the frequently used classes should be registered for auto loading (performance).
	 * It is not necessary to register rarely used classes. They can be found and loaded dynamically.
	 *
	 * @param string $moduleName Name of the module. Can be null if classes are not part of any module
	 * @param array $arClasses Array of classes with class names as keys and paths as values.
	 * @throws LoaderException
	 */
	public static function registerAutoLoadClasses($moduleName, array $arClasses)
	{
		if (empty($arClasses))
			return;

		if (($moduleName !== null) && empty($moduleName))
		{
			throw new LoaderException(sprintf("Module name '%s' is not correct", $moduleName));
		}

		if (!static::$isAutoLoadOn)
		{
			if (!is_null($moduleName) && !isset(static::$arLoadedModulesHolders[$moduleName]))
				throw new LoaderException(sprintf("Holder of module '%s' is not found", $moduleName));

			$documentRoot = static::getDocumentRoot();

			if (!is_null($moduleName))
			{
				foreach ($arClasses as $value)
				{
					if (file_exists($documentRoot."/".self::$arLoadedModulesHolders[$moduleName]."/modules/".$moduleName."/".$value))
						require_once($documentRoot."/".self::$arLoadedModulesHolders[$moduleName]."/modules/".$moduleName."/".$value);
				}
			}
			else
			{
				foreach ($arClasses as $value)
				{
					if (($includePath = self::getLocal($value, $documentRoot)) !== false)
						require_once($includePath);
				}
			}
		}
		else
		{
			foreach ($arClasses as $key => $value)
			{
				$class = ltrim($key, "\\");
				self::$arAutoLoadClasses[strtr($class, static::ALPHA_UPPER, static::ALPHA_LOWER)] = array(
					"module" => $moduleName,
					"file" => $value
				);
			}
		}
	}

	/**
	 * Registers namespaces with custom paths.
	 * e.g. ('Bitrix\Main\Dev', 'main', 'dev/lib')
	 *
	 * @param string $namespace
	 * @param string $module
	 * @param string $path
	 */
	public static function registerNamespace($namespace, $module, $path)
	{
		$namespace = rtrim($namespace, '\\');
		$path = rtrim($path, '/\\');

		$path = static::getDocumentRoot()."/".static::$arLoadedModulesHolders[$module]."/modules/".$module.'/'.$path;

		static::$customNamespaces[$namespace] = $path;
	}

	public static function isAutoLoadClassRegistered($className)
	{
		$className = trim(ltrim($className, "\\"));
		if ($className == '')
			return false;

		$className = strtr($className, static::ALPHA_UPPER, static::ALPHA_LOWER);

		return isset(self::$arAutoLoadClasses[$className]);
	}

	/**
	 * \Bitrix\Main\IO\File -> /main/lib/io/file.php
	 * \Bitrix\IBlock\Type -> /iblock/lib/type.php
	 * \Bitrix\IBlock\Section\Type -> /iblock/lib/section/type.php
	 * \QSoft\Catalog\Tools\File -> /qsoft.catalog/lib/tools/file.php
	 *
	 * @param $className
	 */
	public static function autoLoad($className)
	{
        // die('1?');

		$file = ltrim($className, "\\");    // fix web env
        // echo "file = $file";die;

		$file = strtr($file, static::ALPHA_UPPER, static::ALPHA_LOWER);
        // echo "file = $file";die;

		static $documentRoot = null;
		if ($documentRoot === null)
            // die('static?');
			$documentRoot = static::getDocumentRoot();

        // var_dump(self::$arAutoLoadClasses[$file]);;die; //null
        // var_dump((isset(self::$arAutoLoadClasses[$file])));die; //false

		if (isset(self::$arAutoLoadClasses[$file]))
		{
			$pathInfo = self::$arAutoLoadClasses[$file];
			if ($pathInfo["module"] != "")
			{
				$m = $pathInfo["module"];
				$h = isset(self::$arLoadedModulesHolders[$m]) ? self::$arLoadedModulesHolders[$m] : 'bitrix';
				include_once($documentRoot."/".$h."/modules/".$m."/" .$pathInfo["file"]);
			}
			else
			{
				require_once($documentRoot.$pathInfo["file"]);
			}

			if (class_exists($className))
			{
				return;
			}
		}

        // var_dump(preg_match("#[^\\\\/a-zA-Z0-9_]#", $file));die;    // 0

		if (preg_match("#[^\\\\/a-zA-Z0-9_]#", $file))
			return;

		$tryFiles = [$file];

        // var_dump($tryFiles);die;    // array(1) { [0]=> string(7) "amrmain" }
        // var_dump(substr($file, -5));die;    // string(5) "rmain"


		if (substr($file, -5) == "table")
		{
			// old *Table stored in reserved files
			$tryFiles[] = substr($file, 0, -5);
		}

		foreach ($tryFiles as $file)
		{
			$file = str_replace('\\', '/', $file);
            // var_dump($file);die;    // string(7) "amrmain"

			$arFile = explode("/", $file);
            // var_dump($arFile);die;  // array(1) { [0]=> string(7) "amrmain" }
            // var_dump($arFile);die;

			if ($arFile[0] === "amr")
			{
				array_shift($arFile);

				if (empty($arFile))
					break;

				$module = array_shift($arFile);
                // var_dump($module);//die;
                // var_dump(empty($arFile));die;
                // var_dump($module == null || empty($arFile));die;

				if ($module == null || empty($arFile)) {
                    // echo "break?";die('350');
                    break;
                }
			}
			else
			{
                // die('else?');
				$module1 = array_shift($arFile);
                // var_export($module1); //die('351');
                // var_dump($arFile);  //die;

				$module2 = array_shift($arFile);    // array_shift — Извлекает первый элемент массива
                // var_dump($module2); //die('355');
                // var_dump($module1, $module2, $arFile);
                // die('calling from line 356');

				if ($module1 == null || $module2 == null || empty($arFile))
				{
					break;         // $module2 == null and empty($arFile)
				}

				$module = $module1.".".$module2;
                // var_dump($module);die('365');    // string(8) "amr.main" 365
			}

            // echo "no-break?";die('373');

            // var_dump($module);//die('377');
            // var_dump(self::$arLoadedModulesHolders);die;
            // var_dump(isset(self::$arLoadedModulesHolders[$module]));die;

			if (!isset(self::$arLoadedModulesHolders[$module])) {
                echo "break?";die('380');
				break;
            }


			$filePath = $documentRoot."/".self::$arLoadedModulesHolders[$module]."/modules/".$module."/lib/".implode("/", $arFile).".php";

            // var_dump($filePath);die('389');
            // var_dump(file_exists($filePath));

			if (file_exists($filePath))
			{
                // echo "require?";
				require_once $filePath;
				break;
			}
			else
			{
                // echo "else?";
				// try namespaces with custom path
                // var_dump(static::$customNamespaces);die;

				foreach (static::$customNamespaces as $namespace => $namespacePath)
				{
                    // var_dump($className, $namespace);die;
                    // var_dump(strpos($className, $namespace));die;
					if (strpos($className, $namespace) === 0)
					{
                        // die('0?');
						// found
						$fileParts = explode("/", $file);
                        // var_dump($fileParts);die;

						// cut base namespace
						for ($i=0; $i <= substr_count($namespace, '\\'); $i++)
						{
							array_shift($fileParts);
						}

						// final path
						$filePath = $namespacePath.'/'.implode("/", $fileParts).".php";
                        // var_dump($filePath);die;

						if (file_exists($filePath))
						{
							require_once $filePath;
							break 2;
						}
					}
				}
			}
		}

        // var_dump(($className)); //die('402');  //false
        // var_dump(class_exists($className));die('402');  //false

		// still not found, check for auto-generated entity classes
		if (!class_exists($className))
		{
            // echo $className . '<br>';
			// \Bitrix\Main\ORM\Loader::autoLoad($className); - wtf?
		}

	}

	/**
	 * Checks if file exists in /local or /bitrix directories
	 *
	 * @param string $path File path relative to /local/ or /bitrix/
	 * @param string $root Server document root, default static::getDocumentRoot()
	 * @return string|bool Returns combined path or false if the file does not exist in both dirs
	 */
	public static function getLocal($path, $root = null)
	{
		if ($root === null)
			$root = static::getDocumentRoot();

		if (file_exists($root."/local/".$path))
			return $root."/local/".$path;
		elseif (file_exists($root."/bitrix/".$path))
			return $root."/bitrix/".$path;
		else
			return false;
	}

	/**
	 * Checks if file exists in personal directory.
	 * If $_SERVER["BX_PERSONAL_ROOT"] is not set than personal directory is equal to /bitrix/
	 *
	 * @param string $path File path relative to personal directory
	 * @return string|bool Returns combined path or false if the file does not exist
	 */
	public static function getPersonal($path)
	{
		$root = static::getDocumentRoot();
		$personal = isset($_SERVER["BX_PERSONAL_ROOT"]) ? $_SERVER["BX_PERSONAL_ROOT"] : "";

		if (!empty($personal) && file_exists($root.$personal."/".$path))
			return $root.$personal."/".$path;

		return self::getLocal($path, $root);
	}
}
