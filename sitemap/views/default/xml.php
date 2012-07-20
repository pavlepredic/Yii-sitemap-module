<?php echo '<?xml version="1.0" encoding="utf-8"?>' ?>
 
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
<?php foreach ($urls as $url => $data) : ?>
<url>
	<loc><?php echo $url ?></loc>
<?php if (isset($data['lastmod'])) : ?>
	<lastmod><?php echo $data['lastmod'] ?></lastmod>
<?php endif; ?>
<?php if (isset($data['changefreq'])) : ?>
	<changefreq><?php echo $data['changefreq'] ?></changefreq>
<?php endif; ?>
<?php if (isset($data['priority'])) : ?>
	<priority><?php echo $data['priority'] ?></priority>
<?php endif; ?>
</url>
<?php endforeach; ?>
</urlset> 
