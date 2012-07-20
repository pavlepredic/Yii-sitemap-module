<?php

class DefaultController extends Controller
{
	/**
	 * Displays sitemap in XML or HTML format,
	 * depending on the value of $format parameter
	 * @param string $format
	 */
	public function actionIndex($format = 'xml')
	{
		if ($this->getModule()->actions)
			$urls = $this->getModule()->getSpecifiedUrls();
		else
			$urls = $this->getModule()->getAllUrls();
			
		if ($format == 'xml')
		{
			if (!headers_sent())
				header('Content-Type: text/xml');
			$this->renderPartial('xml', array('urls' => $urls));
		}
		else
			$this->render('html', array('urls' => $urls));
	}
}