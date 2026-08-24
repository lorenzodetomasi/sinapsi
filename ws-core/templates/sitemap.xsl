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
  <urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <xsl:for-each select="url[not(contains(robots,'noindex'))]">
      	<url>
      		<loc><xsl:value-of select="wspath"/></loc>
          <xsl:if test="changefreq != ''">
            <changefreq><xsl:value-of select="changefreq"/></changefreq>
          </xsl:if>
          <xsl:if test="priority != ''">
            <priority><xsl:value-of select="priority"/></priority>
          </xsl:if>
          <xsl:if test="dateModified != ''">
            <lastmod><xsl:value-of select="dateModified"/></lastmod>
          </xsl:if>
      	</url>
    </xsl:for-each>
  </urlset>
  </xsl:template>
</xsl:stylesheet>
