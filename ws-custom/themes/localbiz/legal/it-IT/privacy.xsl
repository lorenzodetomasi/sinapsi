<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0"
  xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
  xmlns:xi="http://www.w3.org/2001/XInclude"
>
<xsl:output
   method="xml"
   indent="yes"
   encoding="utf-8"
/>
  <xsl:template match="/*[1]">
    <sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
      <xsl:for-each select="url">
        	<url>
        		<loc><xsl:value-of select="wspath"/></loc>
            <changefreq><xsl:value-of select="changefreq"/></changefreq>
            <priority><xsl:value-of select="priority"/></priority>
            <lastmod><xsl:value-of select="dateModified"/></lastmod>
            <loc><xsl:value-of select="wspath"/></loc>
        	</url>
      </xsl:for-each>
      <xsl:for-each select="xi:include">
        	<sitemap>
        		<loc><xsl:value-of select="../ws_root_url"/>ws-custom/contents/<xsl:value-of select="@href"/></loc>
        	</sitemap>
      </xsl:for-each>
  </sitemapindex>
  </xsl:template>
</xsl:stylesheet>
