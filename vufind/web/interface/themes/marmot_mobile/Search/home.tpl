{strip}
<div data-role="page" id="Search-home">
	{include file="header.tpl"}
	<div data-role="content">
		{include file="Search/searchbox.tpl"}
		<!-- <ul data-role="listview" data-inset="true" data-dividertheme="b">
			<li data-role="list-divider">{translate text='Find More'}</li>
			<li><a rel="external" href="{$path}/Search/Reserves">{translate text='Course Reserves'}</a></li>
			<li><a rel="external" href="{$path}/Search/NewItem">{translate text='New Items'}</a></li>	 
		</ul>-->
		
		{*
		<ul data-role="listview" data-inset="true" data-dividertheme="b">
			<li data-role="list-divider">{translate text='Need Help?'}</li>
			<li><a href="{$path}/Help/Home?topic=search" data-rel="dialog">{translate text='Search Tips'}</a></li>
			<li><a href="#">{translate text='Ask a Librarian'}</a></li>
			<li><a href="#">{translate text='FAQs'}</a></li>
		</ul>
		*}
		<h3>Featured Searches For Adults</h3>
		<div data-role="controlgroup">
			<a data-role="button" href='{$path}/Search/Results?lookfor=&amp;basicType=Keyword&amp;filter[]=format_category:"Books"&amp;filter[]=publishDate:[2013+TO+*]&amp;filter[]=literary_form_full:"Fiction"&amp;filter[]=target_audience_full:"Adult"'>New Fiction</a>
			<a data-role="button" href='{$path}/Search/Results?lookfor=&amp;basicType=Keyword&amp;filter[]=format_category:"Books"&amp;filter[]=publishDate:[2013+TO+*]&amp;filter[]=literary_form_full:"Non+Fiction"&amp;filter[]=target_audience_full:"Adult"'>New Non-Fiction</a>
			<a data-role="button" href='{$path}/Search/Results?lookfor=&amp;basicType=Keyword&amp;filter[]=format_category:"Movies"&amp;filter[]=publishDate:[2013+TO+*]&amp;filter[]=target_audience_full:"Adult"'>New Movies</a>
			<a data-role="button" href='{$path}/Search/Results?lookfor=&amp;basicType=Keyword&amp;filter[]=format_category:"eBook"&amp;filter[]=publishDate:[2013+TO+*]&amp;filter[]=target_audience_full:"Adult"'>New eBooks</a>
			<a data-role="button" href='{$path}/Search/Results?lookfor=&amp;basicType=Keyword&amp;filter[]=format_category:"Audio+Books"&amp;filter[]=publishDate:[2013+TO+*]&amp;filter[]=target_audience_full:"Adult"'>New Audio Books</a>
		</div>
		<h3>Featured Searches For Kids</h3>
		<div data-role="controlgroup">
			<a data-role="button" href='{$path}/Search/Results?lookfor=&amp;basicType=Keyword&amp;filter[]=format_category:"Books"&amp;filter[]=publishDate:[2013+TO+*]&amp;filter[]=literary_form_full:"Fiction"&amp;filter[]=target_audience_full:"Juvenile"'>New Fiction</a>
			<a data-role="button" href='{$path}/Search/Results?lookfor=&amp;basicType=Keyword&amp;filter[]=format_category:"Books"&amp;filter[]=publishDate:[2013+TO+*]&amp;filter[]=literary_form_full:"Non+Fiction"&amp;filter[]=target_audience_full:"Juvenile"'>New Non-Fiction</a>
			<a data-role="button" href='{$path}/Search/Results?lookfor=&amp;basicType=Keyword&amp;filter[]=format_category:"Movies"&amp;filter[]=publishDate:[2013+TO+*]&amp;filter[]=target_audience_full:"Juvenile"'>New Movies</a>
		</div>
			
	</div>
	{include file="footer.tpl"}
</div>
{/strip}