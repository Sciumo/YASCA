<?php
/*
The folowing 3 functions (&last, myParseXML, and MyXMLtoArray) are written by
gleber at mapnet dot pl. They are available at php.net, in the user contribution section 
at xml_parse_into_struct function page.
*/

function &last(&$array) {
if (!count($array)) return null;
end($array);
return $array[key($array)];
}

function myParseXML(&$vals, &$dom, &$lev) {
   do {
       $curr = current($vals);
       $lev = $curr['level'];
       switch ($curr['type']) {
           case 'open':
               if (isset($dom[$curr['tag']])) {
                   $tmp = $dom[$curr['tag']];
                   if (!$tmp['__multi'])
                       $dom[$curr['tag']] = array('__multi' => true, $tmp);
                   array_push($dom[$curr['tag']], array());
                   $new =& last($dom[$curr['tag']]);
               } else {
                   $dom[$curr['tag']] = array();
                   $new =& $dom[$curr['tag']];
               }
               next($vals);
               myParseXML(&$vals, $new, $lev);
               break;
           case 'cdata':
               break;
           case 'complete':
               if (!isset($dom[$curr['tag']]))
                   $dom[$curr['tag']] = $curr['value'];
               else {
                   if (is_array($dom[$curr['tag']]))
                       array_push($dom[$curr['tag']] , $curr['value']);
                   else
                       array_push($dom[$curr['tag']] = array($dom[$curr['tag']]) , $curr['value']);
               }
               break;
           case 'close':
               return;
       }
   }
   while (next($vals)!==FALSE);
}

function MyXMLtoArray($XML) {
       $xml_parser = xml_parser_create();
       xml_parse_into_struct($xml_parser, $XML, $vals);
       xml_parser_free($xml_parser);
       reset($vals);
       $dom = array(); $lev = 0;
       myParseXML($vals, $dom, $lev);
       return $dom;
}



?>