<?php
/**
 * Module for dynamic sitemap generation.
 * To enable this module, add the following lines to app config:
 * 
 * 	'modules'=>array(
 * 		'sitemap' => array(
 *			'class' => 'ext.sitemap.SitemapModule', 	//or whatever the correct path is
 *			'actions' => array(...), 					//optional
 *			'absoluteUrls' => true|false, 				//optional
 *			'protectedControllers' => array('admin'), 	//optional
 *			'protectedActions' =>array('site/error'), 	//optional
 *			'priority' => '0.5', 						//optional
 *			'changefreq' => 'daily', 					//optional
 *			'lastmod' => '1985-11-05', 					//optional
 *			'cacheId' => 'cache',						//optional
 *			'cachingDuration' => 3600,					//optional
 *		),
 * 	);
 * 
 * If you don't specify any optional parameters, module will generate a sitemap 
 * using all actions that have default values for their arguments (or have no arguments).
 * You may exclude entire controllers (by adding them to 'protectedControllers')
 * and specific actions (by adding them to 'protectedActions').
 * 
 * To specify an exact list of actions, place them in $actions array.
 * Actions may be specified either as a string (eg 'site/index') or an array. 
 * If latter is the case, the provided array MUST contain 'route' key, specifying
 * action route (eg 'site/index') and MAY contain further configuration:
 *  - 'condition' - a piece of PHP code that will be eval'd to determine if that action should be included
 *  - 'prefs' - an array containing preferences used for sitemap creation;
 * 		it contains one or more of the following keys: 'lastmod', 'changefreq' and 'priority';
 * 		any preference that is not specified here will take on a default value,
 * 		as specified in $lastmod, $changefreq and $priority, or - if these are unspecified -
 * 		it will fallback to the following values: 
 * 		'changefreq' => 'always', 'lastmod' => date('Y-m-d'), 'priority' => 0.5
 *  - 'params' - used for supplying action parameters; this may be done in one of two ways:
 * 		a) by using an array, eg:
 * 				'array' => array(
 * 					array('postId' => 1, 'postName' => 'Welcome'), //here we specified postId and postName parameters
 * 					array('postId' => 2, 'postName' => 'FAQ'),
 * 				),
 * 		b) by specifying a model class, eg:
 * 				'model' => array(
 * 					'class' => 'Post',
 * 					'criteria' => array('condition' => 'published > NOW() - INTERVAL 5 DAY'), //optional
 * 					'map' => array( //map parameter names to model attributes
 * 						'postId' => 'id', //value for parameter 'postId' is fetched from 'id' attribute of Post model 
 * 						'postName' => 'name', //value for parameter 'postName' is fetched from 'name' attribute of Post model
 * 					),
 * 				),
 * 
 * Specifying parameters by using a model will result in creating an URL for each model instance stored in DB.
 * You may provide search criteria to use only specific models. Criteria should be provided as an array
 * that will be used to initialize CDbCriteria.
 * 
 * Note: if you provide an $actions array, $protectedActions and $protectedControllers have no effect.
 * 
 * Example:
 * $actions = array(
 *		'site/index', //no configuration specified, this will only work if site/index has default argument values
 *		array(
 *			'route' => 'post/index',
 *			'condition' => 'return !Yii::app()->user->getIsGuest();', //only if user is not guest
 *			'prefs' => array( //specify lastmod, changefreq and priority for this URL only
 *				'lastmod' => '2012-07-01',
 *				'changefreq' => 'daily',
 *				'priority' => 0.7,
 *			),
 *		),
 *		array(
 *			'route' => 'post/view',
 *			'params' => array( //specify action parameters
 *				'array' => array( //parameters provided in an array
 *					array('postId' => 50, 'postName' => 'Welcome'),
 *				),
 *				'model' => array(
 *					'class' => 'Post',
 *					'criteria' => array('condition' => 'published > NOW() - INTERVAL 5 DAY')
 *					'map' => array(
 *						'postId' => 'id',
 *						'postName' => 'name',
 *					),
 *				),				
 *			),
 *		),
 *	);
 *
 * Tip: add the following rules to CUrlManager:
 * 	'sitemap.xml' 	=> 'sitemap/default/index',
 *	'sitemap.html' 	=> 'sitemap/default/index/format/html',
 *
 * Caching:
 * By default, this module will cache sitemap data for an hour.
 * You can disable caching by setting 'cachingDuration' to 0.
 * You may also increase or decrease cache validity.
 * 
 * @author Pavle Predic
 * @version 0.2
 */
class SitemapModule extends CWebModule
{
	/**
	 * Whether or not to use absolute URLs
	 * @var bool
	 */
	public $absoluteUrls = true;
	
	/**
	 * List of protected controllers, eg:
	 * array('admin', 'maintenance')
	 * This has no effect if you have 
	 * specified $actions array
	 * @var array
	 */
	public $protectedControllers = array();
	
	/**
	 * List of protected actions, eg:
	 * array('user/login', 'post/delete')
	 * This has no effect if you have 
	 * specified $actions array
	 * @var array
	 */
	public $protectedActions = array();
	
	/**
	 * List of actions used for generating the sitemap
	 * @var array
	 */
	public $actions = array();
	
	/**
	 * Default sitemap preferences.
	 * @var array
	 */
	public $priority, $changefreq, $lastmod;
	
	/**
	 * ID of the caching component
	 * (defaults to 'cache')
	 * @var string
	 */
	public $cacheId = 'cache';
	
	/**
	 * Number of seconds cached data will remain valid.
	 * Set to 0 to disable caching
	 * @var int
	 */
	public $cachingDuration = 3600;
	
	/**
	 * CCache instance
	 * @var CCache
	 */
	protected $_cache;

	
	public function init()
	{
		$this->setImport(array(
			'sitemap.models.*',
			'sitemap.components.*',
		));
	}
	
	/**
	 * Returns an array of URLs specifed in SitemapModule::$actions, with sitemap preferences.
	 * URLs are returned as keys, preferences as values, eg:
	 * array(
	 * 		'http://example.org/index.html' => array(
	 * 			'lastmod' => '1980-08-31', 
	 * 			'changefreq' => 'daily', 
	 * 			'priority' => '0.8'
	 * 		),
	 * 		'http://example.org/login' => array(
	 * 			'lastmod' => '1985-11-05', 
	 * 			'changefreq' => 'daily', 
	 * 			'priority' => '0.5'
	 * 		),
	 * )
	 * @return array
	 */	
	public function getSpecifiedUrls()
	{
		if ($urls = $this->getCache()->get(__METHOD__))
			return $urls;
		
		$urls = array();
		
		foreach ($this->actions as $action)
		{
			$config = array();
			
			//action may be given as a string and as an array, in which case it must contain 'route' key
			if (is_array($action))
			{
				if (!isset($action['route']))
					throw new CHttpException(500, 'Action configuration must contain a "route" key');
				$config = $action;
				$action = $action['route'];
			}

			//evaluate condition
			if (isset($config['condition']) and !eval($config['condition']))
				continue;
			
			//apply params (if supplied)
			if (isset($config['params']))
			{
				//model used to generate params
				if (isset($config['params']['model']) and isset($config['params']['model']['class']))
				{
					$class = $config['params']['model']['class'];
					/*
					* If we use model from any module, we set in config property 'modelAlias'
					* and import model class
					* For example:
					* array(
				        *     'route' => '/blog/post',
				        *     'params' => array(
				        *       'model' => array(
				        *         'modelAlias' => 'application.modules.blog.models.Blog',
				        *         'class' => 'Blog',
				        *         'criteria' => array('condition' => 'published > NOW() - INTERVAL 5 DAY'),
				        *         'map' => array(
				        *           'postId' => 'id',
				        *           'postName' => 'name',
				        *         ),
				        *       ),
				        *     ),
				        *   ),
					*/
					if (isset($config['params']['model']['modelAlias'])) YiiBase::import($config['params']['model']['modelAlias']);
					if (!class_exists($class))
						throw new CHttpException(500, "Class $class not found");
					$criteria = @$config['params']['model']['criteria'];
		
					//fetch all model instances
					foreach (CActiveRecord::model($class)->findAll($criteria) as $model)
					{
						$args = array();
						//build arguments from model attributes
						foreach ($config['params']['model']['map'] as $param => $attribute)
							$args[$param] = $this->getModelAttribute($model, $attribute);
						
						$this->addUrl($urls, $action, $args, @$config['prefs']);
					}
				}
				//array used to generate params
				elseif (isset($config['params']['array']))
					foreach ($config['params']['array'] as $args)
						$this->addUrl($urls, $action, $args, @$config['prefs']);
			}
			//no params
			else
				$this->addUrl($urls, $action, array(), @$config['prefs']);
		}
		
		$this->getCache()->add(__METHOD__, $urls, $this->cachingDuration);
		return $urls;
	}
	
	/**
	 * Returns an array of all site URLs, with sitemap preferences.
	 * @see self::getSpecifedUrls for a description of the return value
	 * @return array
	 */
	public function getAllUrls()
	{
		if ($urls = $this->getCache()->get(__METHOD__))
			return $urls;
		
		Yii::import('application.controllers.*');
		$urls = array();
		
		$directory = Yii::getPathOfAlias('application.controllers');
		$iterator = new DirectoryIterator($directory);
		foreach ($iterator as /* @var $fileinfo SplFileInfo */ $fileinfo)
		{
			if ($fileinfo->isFile() and $fileinfo->getExtension() == 'php')
			{
				$className = substr($fileinfo->getFilename(), 0, -4); //strip extension
				$class = new ReflectionClass($className);
						
				foreach ($class->getMethods(ReflectionMethod::IS_PUBLIC) as /* @var $method ReflectionMethod */ $method)
				{
					//skip methods that have arguments without default values
					if (!$this->hasDefaultParams($method))
						continue;
						
					$methodName = $method->getName();
					//only take methods that begin with 'action', but skip actions() method
					if (strpos($methodName, 'action') === 0 and $methodName != 'actions')
					{	
						$controller = lcfirst(substr($className, 0, strrpos($className, 'Controller')));
						$action = lcfirst(substr($methodName, 6));
						if (!$this->isProtected($controller, $action))
							$this->addUrl($urls, "$controller/$action");
					}
				}
			}
		}
		
		$this->getCache()->add(__METHOD__, $urls, $this->cachingDuration);
		return $urls;
	}
	
	
	/**
	 * Checks if the provided action is protected
	 * ie if it should be excluded from sitemap
	 * @param string $controller
	 * @param string $action
	 * @return boolean
	 */
	protected function isProtected($controller, $action)
	{
		return in_array($controller, $this->protectedControllers) or 
			in_array("$controller/$action", $this->protectedActions);
	}
	
	/**
	 * Checks if the provided ReflectionMethod
	 * has default values for all its arguments (if any)
	 * @param ReflectionMethod $method
	 * @return boolean
	 */
	protected function hasDefaultParams(ReflectionMethod $method)
	{
		foreach ($method->getParameters() as /* @var $param ReflectionParameter */ $param)
			if (!$param->isDefaultValueAvailable())
				return false;
		return true;
	}
	
	/**
	 * Returns the value of the specified attribute
	 * in the provided model
	 * @param CModel $model
	 * @param string $attribute
	 * @throws CHttpException
	 */
	protected function getModelAttribute($model, $attribute)
	{
		$class = get_class($model);
		if (!$model->hasAttribute($attribute) and !$model->hasProperty($attribute))
			throw new CHttpException(500, "Class $class does not have a property named $attribute");
		return $model->$attribute;
	}
	
	/**
	 * Adds an URL to the specified array.
	 * @param array $urls
	 * @param string $action
	 * @param array $args
	 * @param array $prefs
	 */
	protected function addUrl(&$urls, $action, $args = array(), $prefs = null)
	{
		if ($this->absoluteUrls)
			$url = Yii::app()->createAbsoluteUrl($action, $args);
		else
			$url =  Yii::app()->createUrl($action, $args);		
		
		if (!$prefs)
			$prefs = array();
		
		$defPrefs = array(
			'lastmod' => $this->lastmod ? $this->lastmod : date('Y-m-d'),
			'changefreq' => $this->changefreq ? $this->changefreq : 'always',
			'priority' => $this->priority ? $this->priority : 0.5,
		);
		$prefs = array_merge($defPrefs, $prefs);
		
		$urls[$url] = $prefs;
	}
	
	/**
	 * Returns a CCache instance.
	 * This will either be the cache component specified
	 * in self::$cacheId or an instance of CDummyCache
	 * if no caching is required
	 * @return CCache
	 */
	protected function getCache()
	{
		if (!$this->_cache)
		{
			if ($this->cachingDuration and $this->cacheId)
				$this->_cache = Yii::app()->getComponent($this->cacheId);

			if (!($this->_cache instanceof CCache))
				$this->_cache = new CDummyCache();
		}
		
		return $this->_cache;
	}
}
