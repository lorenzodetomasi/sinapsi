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
    <xsl:apply-templates/>
  </xsl:template>
  <xsl:template match="hasMenuSection">
    <section>
      <h1><xsl:value-of select="name"/></h1>
      <h2><xsl:value-of select="description"/>
      <ul>
        <xsl:apply-templates select="hasMenuItem"/>
      </ul>
    </section>
  </xsl:template>
  <xsl:template match="hasMenuItem">
    <li class="flex align-baseline">
      <img src="" />
      <div class="flex1">
        <h2 itemprop="name"></h2>
        <p itemprop="description"></p>
      </div>
      <div itemprop="offers">
        <p></p>
      </div>
    </li>
  </xsl:template>
</xsl:stylesheet>
