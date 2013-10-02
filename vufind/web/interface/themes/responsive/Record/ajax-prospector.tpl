<InProspector><![CDATA[{if is_array($prospectorResults) && count($prospectorResults) > 0}
{if $showProspectorTitlesAsTab == 0}
<div id='prospectorSidebarResults'>
<img id='prospectorMan' src='{$path}/interface/themes/marmot/images/prospector_man_sidebar.png'/>
<div id='prospectorSearchResultsTitle'>{translate text="In Prospector"}</div>
<div class='clearer'>&nbsp;</div>
</div>
{/if}
<ul class="similar unstyled list-striped">
  {foreach from=$prospectorResults item=prospectorTitle}
  {if $similar.recordId != -1}
  <li class="prospectorTitle {if $prospectorTitle.isCurrent}currentRecord{/if}">
    <a href="{$prospectorTitle.link}" rel="external" onclick="window.open (this.href, 'child'); return false"><h5>{$prospectorTitle.title|removeTrailingPunctuation|escape}</h5></a>

	  <dl class="dl-horizontal">
		  {if $prospectorTitle.author}<dt><small>{translate text='By'}</small></dt><dd><small>{$prospectorTitle.author|escape}</small></dd>{/if}
		  {if $prospectorTitle.pubDate}<dt><small>{translate text='Published'}</small></dt><dd><small>{$prospectorTitle.pubDate|escape}</small></dd>{/if}
		  {if $prospectorTitle.format}<dt><small>{translate text='Format'}</small></dt><dd><small>{$prospectorTitle.format|escape}</small></dd>{/if}
	  </dl>

  </li>
  {/if}
  {/foreach}
</ul>
{/if}]]></InProspector>
<ProspectorRecordId>
{*The id of the record within Prospector or blank if it is not available in prospector.*}
{$prospectorDetails.recordId}
</ProspectorRecordId>
<NumOwningLibraries>
{*The number of libraries that hold the record within prospector.*}
{$prospectorDetails.numLibraries}
</NumOwningLibraries>
<OwningLibraries>
{*The libraries that hold the record within prospector.*}
{foreach from=$prospectorDetails.owningLibraries item=owningLibrary}
  <OwningLibrary>{$owningLibrary}</OwningLibrary>
{/foreach}
</OwningLibraries>
<OwningLibrariesFormatted><![CDATA[
{if strlen($prospectorDetails.recordId) > 0}
<div id='prospectorAvailabilityTitle'>Other Sources</div>
<div id='prospectorAvailability'>
  {if $prospectorDetails.prospectorEncoreUrl}<a href="{$prospectorDetails.prospectorEncoreUrl}" rel="external" onclick="window.open (this.href, 'child'); return false">{/if}Available in Prospector{if $prospectorTitle.link}</a>{/if}
  <span class='prospectorRequest'><a class='holdRequest' href='#' onclick="createInnreachRequestWindow('{$prospectorDetails.requestUrl}')" rel="external" onclick="window.open (this.href, 'child'); return false">Request from Prospector</a></span>
</div>
<div id='prospectorItemCount'>
{$prospectorDetails.owningLibraries|@count} Prospector libraries have this item 
</div> 
<div id='prospectorLibraries'>
{foreach from=$prospectorDetails.owningLibraries item=owningLibrary}
  <span class='prospectorLibrary'>♦&nbsp;{$owningLibrary}</span>
{/foreach}
</div>
{/if}
]]></OwningLibrariesFormatted>
<ProspectorClassicUrl>{$prospectorDetails.prospectorClassicUrl}</ProspectorClassicUrl>
<ProspectorEncoreUrl>{$prospectorDetails.prospectorEncoreUrl}</ProspectorEncoreUrl>