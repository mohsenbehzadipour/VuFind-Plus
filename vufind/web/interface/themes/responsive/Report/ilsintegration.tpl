{strip}
<script type="text/javascript" src="/js/highcharts/highcharts.js"></script>
<script type="text/javascript" src="/js/analyticReports.js"></script>

<div class="row-fluid">
	{include file="Report/reportSwitcher.tpl"}
	{include file="Report/analyticsFilters.tpl"}
</div>

<h2 class="clearer">Holds</h2>
<div class="row-fluid">
	{* Holds by Result*}
	<div id="holdsByResultContainer" class="span4">
		<div id="holdsByResultChart" class="dashboardChart">
		</div>
		<div class="detailedReportLink"><a href="/Report/DetailedReport?source=holdsByResult{if $filterString}&amp;{$filterString|replace:"&":"&amp;"}{/if}">Detailed Report</a></div>
	</div>
	{* Sessions by number of holds placed (1, 2, 3, 4, etc) *}
	<div id="holdsPerSessionContainer" class="span4">
		<div id="holdsBySessionChart" class="dashboardChart">
		</div>
		<div class="detailedReportLink"><a href="/Report/DetailedReport?source=holdsPerSession{if $filterString}&amp;{$filterString|replace:"&":"&amp;"}{/if}">Detailed Report</a></div>
	</div>
	{* Sessions by number of holds canceled (1, 2, 3, 4, etc) *}
	<div id="holdsCancelledPerSessionContainer" class="span4">
		<div id="holdsCancelledBySessionChart" class="dashboardChart">
		</div>
		<div class="detailedReportLink"><a href="/Report/DetailedReport?source=holdsCancelledPerSession{if $filterString}&amp;{$filterString|replace:"&":"&amp;"}{/if}">Detailed Report</a></div>
	</div>
</div>

<div class="row-fluid">
	{* Sessions by number of holds updated (1, 2, 3, 4, etc) *}
	<div id="holdsUpdatedPerSessionContainer" class="span4">
		<div id="holdsUpdatedBySessionChart" class="dashboardChart">
		</div>
		<div class="detailedReportLink"><a href="/Report/DetailedReport?source=holdsUpdatedPerSession{if $filterString}&amp;{$filterString|replace:"&":"&amp;"}{/if}">Detailed Report</a></div>
	</div>
	{* Sessions by number of holds failed holds (1, 2, 3, 4, etc) *}
	<div id="holdsFailedPerSessionContainer" class="span4">
		<div id="holdsFailedBySessionChart" class="dashboardChart">
		</div>
		<div class="detailedReportLink"><a href="/Report/DetailedReport?source=holdsFailedPerSession{if $filterString}&amp;{$filterString|replace:"&":"&amp;"}{/if}">Detailed Report</a></div>
	</div>
	{* Top Titles with Failed Holds *}
	{* Holds by Patron Type *}
	{* Holds by Home Libary *}
	{* Holds by Home Libary *}
</div>

<h2 class="clearer">Renewals</h2>
{* Sessions by number of renewals (1, 2, 3, 4, etc) *}
{* Sessions by number of failed renewals (1, 2, 3, 4, etc) *}
<h2 class="clearer">Reading History</h2>
{* Sessions with Reading History Updates *}
{* Sessions with Reading History View *}
{* Trend of *}
<h2 class="clearer">Profile</h2>
{* Sessions with Profile View *}
{* Sessions with Profile Update *}
<h2 class="clearer">Self Registration</h2>

{/strip}
{* Setup charts for rendering*}
<script type="text/javascript">
{literal}

$(document).ready(function() {
	setupPieChart("holdsByResultChart", "holdsByResult", "Holds By Result", "% Used");
	setupPieChart("holdsBySessionChart", "holdsPerSession", "Holds Per Session", "% Used");
	setupPieChart("holdsCancelledBySessionChart", "holdsCancelledPerSession", "Holds Cancelled Per Session", "% Used");
	setupPieChart("holdsUpdatedBySessionChart", "holdsUpdatedPerSession", "Holds Updated Per Session", "% Used");
	setupPieChart("holdsFailedBySessionChart", "holdsFailedPerSession", "Holds Failed Per Session", "% Used");
});
{/literal}
</script>