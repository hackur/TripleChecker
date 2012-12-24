<?php

require_once( "arc/ARC2.php" );
require_once( "Graphite/Graphite.php" );
require_once( "template.php" );

if( !isset( $_GET['uri'] ) )
{
	render_header("front","RDF Triple-Checker");
	print "<h1>RDF Triple-Checker</h1>";
?>
<p>This tool helps find typos and common errors in RDF data.</p>

<p>Enter a URI or URL which will resolve to some RDF Triples.</p>
<form>
<table width='80%' style='margin:auto'>
<tr>
<td align='right'>URI/URL:</td><td width='100%'><input id='uri' name='uri' value='' style='width:100%' /></td></tr>
</table>
<div><input style='margin-top:0.5em' value='Check' type='submit' /></div>
</form>

<p>Drag this <a class="bookmarklet" href="javascript:window.location = &quot;http://graphite.ecs.soton.ac.uk/checker/?uri=&quot;+encodeURIComponent(window.location.href);">3-Check</a> bookmarklet to your bookmarks to create a quick button for sending your current URL to triple-checker.</p>


<?php
	render_footer();
	print "<script type='text/javascript'>document.getElementById('uri').focus()</script>";
	exit;
}
$check_uri = $_GET["uri"];

######################################################
# Load data
######################################################

$opts = array();
$opts['http_accept_header']= 'Accept: application/rdf+xml; q=0.9, text/turtle; q=0.8, */*; q=0.1';

$parser = ARC2::getRDFParser($opts);

$parser->parse( $check_uri );

$errors = $parser->getErrors();
$parser->resetErrors();
render_header( "results", htmlspecialchars( $check_uri )." - RDF Triple-Checker");
print "<h1>RDF Triple-Checker</h1>";
print "<form>
<table width='80%' style='margin:auto'>
<tr>
<td align='right'>URI/URL:</td><td width='100%'><input id='uri' name='uri' value='".htmlspecialchars($check_uri)."' style='width:100%' /></td></tr>
</table>
<div><input style='margin-top:0.5em' value='Check Again' type='submit' /></div>
</form>";

if( sizeof($errors) )
{
	print "<div class='error'><h3>Error loading: ".htmlspecialchars($check_uri)."</h3><ul><li>".join( "</li><li>",$errors)."</li></ul></div>";
	render_footer();
	exit;
}

$triples = $parser->getTriples();
$n = sizeof( $triples );
print "<div class='message'>Loaded $n triples</div>";

######################################################
# Find Namespaces, Classes, Predicates
######################################################

$namespaces = array();
foreach( $triples as $t )
{
	if( $t["p"] == "http://www.w3.org/1999/02/22-rdf-syntax-ns#type" )
	{
		list( $ns, $term ) = split_uri( $t["o"] );
		@$namespaces[$ns]["class"][$term]++;
	}
	list( $ns, $term ) = split_uri( $t["p"] );
	@$namespaces[$ns]["property"][$term]++;
}

print "<table class='results'>";
print "<tr>";
print "<th>Count</th>";
print "<th>Type</th>";
print "<th style='text-align:right'>Namespace</th>";
print "<th>Term</th>";
print "<th colspan='2'>Looks Legit?</th>";
print "</tr>";
foreach( $namespaces as $ns=>$terms )
{	
	$opts = array();
	$opts['http_accept_header']= 'Accept: application/rdf+xml; q=0.9, text/turtle; q=0.8, */*; q=0.1';
	$parser = ARC2::getRDFParser($opts);

	$parser->parse( $ns );
	$errors = $parser->getErrors();
	$loaded_ns = true;
	$terms_in_ns = array();
	if( sizeof($errors) )
	{
		$loaded_ns = false;
		$ns_error = "Failed to load namespace";
	}
	else
	{
		$ns_triples = $parser->getTriples();
	
		$classes = array(
	"http://www.w3.org/1999/02/22-rdf-syntax-ns#Property" => "property",
	"http://www.w3.org/2000/01/rdf-schema#Class" => "class",
	"http://www.w3.org/2002/07/owl#ObjectProperty" => "property",
	"http://www.w3.org/2002/07/owl#DatatypeProperty" => "property",
	"http://www.w3.org/2002/07/owl#Class" => "class",
		);

		foreach( $ns_triples as $t )
		{
			if( $t["p"] == "http://www.w3.org/1999/02/22-rdf-syntax-ns#type" )
			{
				if( isset( $classes[ $t["o"] ] ) )
				{
					$terms_in_ns[ $t["s"] ][ $classes[ $t["o"] ] ] = true;
				}
			}
		}
		if( sizeof( $ns_triples ) == 0 )
		{
			$loaded_ns = false;
			$ns_error = "Namespace returned no triples";
		}
		if( sizeof( $terms_in_ns ) == 0 )
		{
			$loaded_ns = false;
			$ns_error = "No vocab terms found in namespace";
		}
	}

	
	foreach( $terms as $type=>$list )
	{
		foreach( $list as $term=>$count )
		{
			if( !$loaded_ns ) 
			{
				print "<tr class='unknown'>";
			}
			elseif( !isset( $terms_in_ns[$ns.$term] ) )
			{
				print "<tr class='bad'>";
			}
			elseif( !isset( $terms_in_ns[$ns.$term][$type] ) )
			{
				print "<tr class='bad'>";
			}
			else
			{
				print "<tr class='good'>";
			}
			print "<td class='count'>$count</td>";
			print "<td class='type'>$type</td>";
			print "<td class='namespace'>$ns</td>";
			print "<td class='term'>$term</td>";
			if( !$loaded_ns ) 
			{
				print "<td class='legit'>?</td>";
				print "<td class='comment'> - $ns_error.</td>";
			}
			elseif( !isset( $terms_in_ns[$ns.$term] ) )
			{
				print "<td class='legit'>BAD</td>";
				print "<td class='comment'> - Term is not defined by namespace.</td>";
			}
			elseif( !isset( $terms_in_ns[$ns.$term][$type] ) )
			{
				print "<td class='legit'>BAD</td>";
				print "<td class='comment'> - Term is incorrect type.</td>";
			}
			else
			{
				print "<td class='legit'>OK</td>";
				print "<td class='comment'> - Looks good.</td>";
			}
				
			print "</tr>";
		}
	}
}
print "</table>";
print "<hr size='1' />";
render_footer();

exit;
function split_uri( $uri)
{
	$uri = preg_match( '/^(.*[#\/])([^#\/]*)$/', $uri, $parts );
	return array( $parts[1], $parts[2] );
}

