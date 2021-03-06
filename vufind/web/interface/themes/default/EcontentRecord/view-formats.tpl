{strip}
{if count($holdings) > 0}
	{foreach from=$holdings item=eContentItem key=index}
	<div class="eContentHolding">
		<div class="eContentHoldingHeader">
			<span class="eContentHoldingFormat">{$eContentItem->getDisplayFormat()}</span>
			{if $showEContentNotes} {$eContentItem->notes}{/if}
			<div class="eContentFormatUsage">
				{assign var="displayFormat" value=$eContentItem->getDisplayFormat()|substr:0:1}
				<a href="#" onclick="return ajaxLightbox('/Help/eContentHelp?lightbox=true&id={$id}&itemId={$eContentItem->id}');">
					{$eContentItem->getHelpText()}
				</a>
			</div>
			<div class="eContentHoldingUsage">
				{assign var="formatNotes" value=$eContentItem->getFormatNotes()}
				{assign var="usageNotes" value=$eContentItem->getUsageNotes()}
				{$formatNotes}
				{if $formatNotes && $usageNotes}<br/>{/if}
				{$usageNotes}
			</div>
		</div>
		<div class="eContentHoldingNotes">
				{if $eContentItem->size != 0 && strcasecmp($eContentItem->size, 'unknown') != 0}
				Size: {$eContentItem->getSize()|file_size}<br/>
				{/if}
		</div>
		<div class="eContentHoldingActions">
			{if $eContentItem->sampleUrl_1}
				<a href="{$eContentItem->sampleUrl_1}" class="button">{translate text="Sample"}: {$eContentItem->sampleName_1}</a>
			{/if}
			{if $eContentItem->sampleUrl_2}
				<a href="{$eContentItem->sampleUrl_2}" class="button">{translate text="Sample"}: {$eContentItem->sampleName_2}</a>
			{/if}
			{* Options for the user to view online or download *}
			{foreach from=$eContentItem->links item=link}
				<a href="{if $link.url}{$link.url}{else}#{/if}" {if $link.onclick}onclick="{$link.onclick}"{/if} class="button">{$link.text}</a>
			{/foreach}
			{if $user && $user->hasRole('epubAdmin') && ($record->accessType != 'external' && strlen($record->ilsId) > 0)}
				<a href="#" onclick="return editItem('{$id}', '{$eContentItem->id}')" class="button">Edit</a>
				<a href="#" onclick="return deleteItem('{$id}', '{$eContentItem->id}')" class="button">Delete</a>
			{/if}
		</div>
	</div>
	{/foreach}
	
	<div id="formatHelp">
		Need help?  We have <a href="{$path}/Help/eContentHelp" onclick="return ajaxLightbox('{$path}/Help/eContentHelp?lightbox=true')">step by step instructions</a> for most formats and devices <a href="{$path}/Help/eContentHelp" onclick="return ajaxLightbox('{$path}/Help/eContentHelp?lightbox=true')">here</a>.<br/>
		If you still need help after following the instructions, please fill out this <a href="{$path}/Help/eContentSupport" onclick="return showEContentSupportForm()">support form</a>. 
	</div>
{else}
	No Copies Found
{/if}

{assign var=firstItem value=$holdings.0}
{if strcasecmp($source, 'OverDrive') != 0 && $user && $user->hasRole('epubAdmin')}
	<hr />
	<p>
	<a href="#" onclick="return addItem('{$id}');" class="button">Add Format</a>
	</p>
{/if}

{/strip}