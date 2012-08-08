#ABOUT:

This is a module for Yii framework (http://www.yiiframework.com/). The purpose of this module is
to generate a sitemap (in xml or html format) for your Yii web application. It is entirely self-contained
and requires very little configuration. By default, it will create a sitemap using all your controller actions.
This may be fine-tuned by specifying module attributes, as described below.

#HOW TO USE:
To enable this module, add the following lines to app config:
```php
'modules'=>array(
	'sitemap' => array(
		'class' => 'ext.sitemap.SitemapModule', 	//or whatever the correct path is
		'actions' => array(...), 					//optional
		'absoluteUrls' => true|false, 				//optional
		'protectedControllers' => array('admin'), 	//optional
		'protectedActions' =>array('site/error'), 	//optional
		'priority' => '0.5', 						//optional
		'changefreq' => 'daily', 					//optional
		'lastmod' => '1985-11-05', 					//optional
		'cacheId' => 'cache',						//optional
		'cachingDuration' => 3600,					//optional			
	),
);
```

If you don't specify any optional parameters, module will generate a sitemap 
using all actions that have default values for their arguments (or have no arguments).
You may exclude entire controllers (by adding them to 'protectedControllers')
and specific actions (by adding them to 'protectedActions').

To specify an exact list of actions, place them in $actions array.
Actions may be specified either as a string (eg 'site/index') or an array. 
If latter is the case, the provided array MUST contain 'route' key, specifying
action route (eg 'site/index') and MAY contain further configuration:
- 'condition' - a piece of PHP code that will be eval'd to determine if that action should be included
- 'prefs' - an array containing preferences used for sitemap creation;
		it contains one or more of the following keys: 'lastmod', 'changefreq' and 'priority';
		any preference that is not specified here will take on a default value,
		as specified in $lastmod, $changefreq and $priority, or - if these are unspecified -
		it will fallback to the following values: 
```
'changefreq' => 'always', 'lastmod' => date('Y-m-d'), 'priority' => 0.5
```
- 'params' - used for supplying action parameters; this may be done in one of two ways:
		a) by using an array, eg:
```
'array' => array(
	array('postId' => 1, 'postName' => 'Welcome'), //here we specified postId and postName parameters
	array('postId' => 2, 'postName' => 'FAQ'),
),
```
		b) by specifying a model class, eg:
```php
'model' => array(
	'class' => 'Post',
	'criteria' => array('condition' => 'published > NOW() - INTERVAL 5 DAY'), //optional
	'map' => array( //map parameter names to model attributes
		'postId' => 'id', //value for parameter 'postId' is fetched from 'id' attribute of Post model 
		'postName' => 'name', //value for parameter 'postName' is fetched from 'name' attribute of Post model
	),
),
```

Specifying parameters by using a model will result in creating an URL for each model instance stored in DB.
You may provide search criteria to use only specific models. Criteria should be provided as an array
that will be used to initialize CDbCriteria.

Note: if you provide an $actions array, $protectedActions and $protectedControllers have no effect.

#Example:
```php
$actions = array(
	'site/index', //no configuration specified, this will only work if site/index has default argument values
	array(
		'route' => 'post/index',
		'condition' => 'return !Yii::app()->user->getIsGuest();', //only if user is not guest
		'prefs' => array( //specify lastmod, changefreq and priority for this URL only
			'lastmod' => '2012-07-01',
			'changefreq' => 'daily',
			'priority' => 0.7,
		),
	),
	array(
		'route' => 'post/view',
		'params' => array( //specify action parameters
			'array' => array( //parameters provided in an array
				array('postId' => 50, 'postName' => 'Welcome'),
			),
			'model' => array(
				'class' => 'Post',
				'criteria' => array('condition' => 'published > NOW() - INTERVAL 5 DAY')
				'map' => array(
					'postId' => 'id',
					'postName' => 'name',
				),
			),				
		),
	),
);
```

Tip: add the following rules to CUrlManager:
```php
'sitemap.xml' 	=> 'sitemap/default/index',
'sitemap.html' 	=> 'sitemap/default/index/format/html',
```
#Caching:
By default, this module will cache sitemap data for an hour.
You can disable caching by setting 'cachingDuration' to 0.
You may also increase or decrease cache validity.