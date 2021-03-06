<Suggestions>
<![CDATA[
{if $suggestions}
  <div id='suggestionsListWrapper'>
  <ul id='suggestionsList' class='jcarousel-skin-tango'>
  {foreach from=$suggestions item=suggestion name="recordLoop"}
    {counter name=starCounter assign=starIndex}
    <li class='suggestedTitle'>
      {* Display the title with a link to the actual record. *}
      <div class='suggestedTitleTitle'>
      <a href='{$path}/Record/{$suggestion.titleInfo.id}'>{$suggestion.titleInfo.title|regex_replace:"/(\/|:)$/":""|escape}</a>
      </div>
      {* Display the book cover *}
      <div class='suggestedTitleInfo'>
        {* Display the cover *}
        <a href='{$path}/Record/{$suggestion.titleInfo.id}'>
        <img src="{$coverUrl}/bookcover.php?id={$suggestion.titleInfo.id}&amp;isn={$suggestion.titleInfo.isbn10|@formatISBN}&amp;size=small&amp;upc={$suggestion.titleInfo.upc}&amp;issn={$suggestion.titleInfo.issn}&amp;category={$suggestion.titleInfo.format_category|escape:"url"}" class="suggestionImage" alt="{translate text='Cover Image'}" />
        </a>
        {* Let the user rate this title *}
        {if $showRatings == 1}
        {include file="Record/title-rating.tpl" ratingClass="suggestionRating" recordId=$suggestion.titleInfo.id shortId=$suggestion.titleInfo.shortId starPostFixId=_suggestion$starIndex}
        {/if}
      </div>

      {* Show why this was recommended (up to 3) *}
      <div class='suggestionBasedOn'>
        <div class='suggestionBasedOnLabel'>Because you enjoyed:</div>
        {foreach from=$suggestion.basedOn item=basedOnTitle}
           <div class='sugggestionBasedOnTitle'><a href='{$path}/Record/{$basedOnTitle.id}'>{$basedOnTitle.title|regex_replace:"/(\/|:)$/":""|escape}</a></div>
        {/foreach}
        
      </div>
    </li>
  {/foreach}
  </ul>
  </div>
  <script type="text/javascript">
  $('#suggestionsList').jcarousel();
  </script>
{else}
  <div id='suggestions'>
  We could not find any suggestions for you.  Please rate more titles so we can give you suggestions for titles you may like.
  </div>
{/if}
]]></Suggestions>