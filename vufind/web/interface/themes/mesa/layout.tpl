<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html lang="{$userLang}">
  <head>
    <meta http-equiv="Content-Type" content="text/html;charset=utf-8" >
    <meta http-equiv="X-UA-Compatible" content="IE=8" >
    <title>{$pageTitle|truncate:64:"..."}</title>
    {if $addHeader}{$addHeader}{/if}
    <link rel="search" type="application/opensearchdescription+xml" title="Library Catalog Search" href="{$url}/Search/OpenSearch?method=describe" >
    {if $consolidateCss}
      {css filename="consolidated_css.css"}
    {else}
	    {css filename="jqueryui.css"}
    	{css media="screen" filename="styles.css"}
    	{css media ="screen" filename="book-bag.css"}
    {/if}
    {css media="print" filename="print.css"}
    	
    <script  type="text/javascript">
      path = '{$url}';
      loggedIn = {if $user}true{else}false{/if}
    </script>
    	<link rel="stylesheet" type="text/css" media="all" href="http://mesacountylibraries.org/wp-content/themes/mcpl/styles/superfish.css" />      
	<script type="text/javascript" src="http://mesacountylibraries.org/wp-content/themes/mcpl/js/jquery-ui-1.8.13.custom.min.js"></script>
	<script type="text/javascript" src="http://mesacountylibraries.org/wp-content/themes/mcpl/js/jquery.tools.min.js"></script>
	<script type="text/javascript" src="http://mesacountylibraries.org/wp-content/themes/mcpl/js/superfish.js"></script>
    {if $consolidateJs}
    	<script type="text/javascript" src="{$path}/API/ConsolidatedJs"></script>
    {else}
	    <script  type="text/javascript" src="{$path}/js/yui/yahoo-dom-event.js"></script>
	    <script  type="text/javascript" src="{$path}/js/yui/yahoo-min.js"></script>
	    <script  type="text/javascript" src="{$path}/js/yui/event-min.js"></script>
	    <script  type="text/javascript" src="{$path}/js/yui/connection-min.js"></script>
	    <script  type="text/javascript" src="{$path}/js/yui/dragdrop-min.js"></script>
	    <script  type="text/javascript" src="{$path}/js/scripts.js"></script>
	    <script  type="text/javascript" src="{$path}/js/rc4.js"></script>
	
	    
	    <script  type="text/javascript" src="{$path}/js/jquery-1.5.1.min.js"></script>
	    <script  type="text/javascript" src="{$path}/js/jqueryui/jquery-ui-1.8.18.custom.min.js"></script>
	    
	    {if $enableBookCart}
	    <script  type="text/javascript" src="{$path}/js/bookcart/jquery.blockUI.js"></script>
	    <script  type="text/javascript" src="{$path}/js/bookcart/json2.js"></script>
	    <script  type="text/javascript" src="{$path}/js/bookcart/jquery.cookie.js"></script>
		  <script  type="text/javascript" src="{$path}/js/bookcart/bookcart.js"></script>
		  {/if}
	    
	    <script  type="text/javascript" src="{$path}/js/ajax.yui.js"></script>
	    <script  type="text/javascript" src="{$path}/js/dropdowncontent.js"></script>
	    <script  type="text/javascript" src="{$path}/js/tabs/tabcontent.js"></script>

		  <script type="text/javascript" src="{$path}/js/starrating/jquery.rater.js"></script>
		  
		  <script type="text/javascript" src="{$path}/js/autofill.js"></script>
		
		  <script type="text/javascript" src="{$path}/js/tooltip/lib/jquery.bgiframe.js"></script>
		  <script type="text/javascript" src="{$path}/js/tooltip/lib/jquery.dimensions.js"></script>
		  <script type="text/javascript" src="{$path}/js/tooltip/jquery.tooltip.js"></script>
		  
		  <script type="text/javascript" src="{$path}/js/validate/jquery.validate.min.js"></script>
		  
			<script  type="text/javascript" src="{$path}/js/jcarousel/lib/jquery.jcarousel.min.js"></script>
			<script  type="text/javascript" src="{$path}/js/ajax_common.js"></script>
			<script  type="text/javascript" src="{$path}/services/Search/ajax.js"></script>
			<script  type="text/javascript" src="{$path}/services/Record/ajax.js"></script>
			<script  type="text/javascript" src="{$path}/js/description.js"></script>
		  
    {/if}
    
    {* Files that should not be combined *}
    {if false && !$productionServer}
    <script  type="text/javascript" src="{$path}/js/errorHandler.js"></script>
    {/if}
    {if $includeAutoLogoutCode == true}
    <script  type="text/javascript" src="{$path}/js/jquery.idle-timer.js"></script>
    <script  type="text/javascript" src="{$path}/js/autoLogout.js"></script>
    {/if}
  
    
    {if isset($theme_css)}
    <link rel="stylesheet" type="text/css" href="{$theme_css}" >
    {/if}
  </head>

  <body class="{$module} {$action}" onload="{literal}if(document.searchForm != null && document.searchForm.lookfor != null){ document.searchForm.lookfor.focus();} if(document.loginForm != null){document.loginForm.username.focus();}{/literal}">
   {include file="bookcart.tpl"}
  
    <!-- Current Physical Location: {$physicalLocation} -->
    {* LightBox *}
    <div id="lightboxLoading" style="display: none;">{translate text="Loading"}...</div>
    <div id="lightboxError" style="display: none;">{translate text="lightbox_error"}</div>
    <div id="lightbox" onclick="hideLightbox(); return false;"></div>
    <div id="popupbox" class="popupBox"><b class="btop"><b></b></b></div>
    {* End LightBox *}
    
    <div class="searchheader">
      <div class="searchcontent">
        {include file='login-block.tpl'}
		{include file ='mesaheader.tpl'}
        

        <br clear="all">
        
        {if $showTopSearchBox}
          <div id='searchbar'>
          {if $pageTemplate != 'advanced.tpl'}
            {include file="searchbar.tpl"}
          {/if}
          </div>
        {/if}
      </div>
    </div>
    
    {if $showBreadcrumbs}
    <div class="breadcrumbs">
      <div class="breadcrumbinner">
        <a href="{$homeBreadcrumbLink}">{translate text="Home"}</a> <span>&gt;</span>
        {include file="$module/breadcrumbs.tpl"}
      </div>
    </div>
    {/if}
    
    <div id="doc2" class="yui-t4"> {* Change id for page width, class for menu layout. *}

      {if $useSolr || $useWorldcat || $useSummon}
      <div id="toptab">
        <ul>
          {if $useSolr}
          <li{if $module != "WorldCat" && $module != "Summon"} class="active"{/if}><a href="{$url}/Search/Results?lookfor={$lookfor|escape:"url"}">{translate text="University Library"}</a></li>
          {/if}
          {if $useWorldcat}
          <li{if $module == "WorldCat"} class="active"{/if}><a href="{$url}/WorldCat/Search?lookfor={$lookfor|escape:"url"}">{translate text="Other Libraries"}</a></li>
          {/if}
          {if $useSummon}
          <li{if $module == "Summon"} class="active"{/if}><a href="{$url}/Summon/Search?lookfor={$lookfor|escape:"url"}">{translate text="Journal Articles"}</a></li>
          {/if}
        </ul>
      </div>
      <div style="clear: left;"></div>
      {/if}

      {include file="$module/$pageTemplate"}
      
      {if $hold_message}
        <script type="text/javascript">
        lightbox();
        document.getElementById('popupbox').innerHTML = "{$hold_message|escape:"javascript"}";
        </script>
      {/if}

      <div id="ft">
      {include file="footer.tpl"}
      </div> {* End ft *}

    </div> {* End doc *}
 {include file ='mesafooter.tpl'}
  </body>
</html>