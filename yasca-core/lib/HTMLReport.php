<?php
require_once("lib/common.php");
require_once("lib/Report.php");
require_once("lib/Result.php");
require_once("lib/Yasca.php");
/**
 * HTMLReport Class
 *
 * This class renders scan results as rich HTML.
 * @author Michael V. Scovetta <scovetta@users.sourceforge.net>
 * @version 2.0
 * @license see doc/LICENSE
 * @package Yasca
 */
class HTMLReport extends Report {
    /**
     * The default extension used for reports of this type.
     */
    public $default_extension = "html";
    
    /**
     * Executes an HTMLReport, with output going to $options['output']
     */ 
    public function execute() {
        if (!$handle = $this->create_output_handle()) return;
        
        fwrite($handle, $this->get_preamble());
        $row_id_format = "%0" . ceil(log10(count($this->results))) . "d";
        $num_results_written = 0;
        foreach ($this->results as $result) {
            if (!$this->is_severity_sufficient($result->severity))
                continue;
            $filename = $result->filename;
            $pinfo = pathinfo($filename);
            $ext = $pinfo['extension'];
            if (isset($result->custom['translation'])) {
                $t =& $result->custom['translation'];
                $filename = $t[basename($filename, ".$ext")];
            }

            $source_context = "";
            if (is_array($result->source_context)) {
                foreach ($result->source_context as $context) {
                    $context = preg_replace('/[\r\n]/', "", $context);
                    $context = substr(trim($context), 0, 76);
                    $source_context .= htmlentities($context) . "~~~";
                }
            }
            $row_id = sprintf($row_id_format, ++$num_results_written);
            $category_link = $result->category_link;
            $category = $result->category;
            $plugin_name = $result->plugin_name;
            $source_code_class = ($result->is_source_code ? "code" : "");
            $source = $result->source;
            $severity_description = $this->get_severity_description($result->severity);
            $filename_base = basename($filename);
            $line_number = $result->line_number;
            $line_number_field = $result->line_number > 0 ? ":" . $result->line_number : "";
            $category_link_field = "<a href=\"$category_link\" target=\"_blank\">$category</a>";
            if ($category_link == "") $category_link_field = $category;
            
            fwrite($handle,
                    "<tr>" .
                    " <td title=\"Severity: $severity_description\nPlugin: $plugin_name\" class=\"severity_$severity_description\">$row_id</td>\n" . 
                    " <td nowrap>$category_link_field</td>\n" .
                    " <td>");

            if ($result->is_source_code || ($line_number != 0 && strlen(trim($source_context)) > 0)) {
                fwrite($handle, 
                    "<div class=\"snippet_anchor\" onclick=\"show_code_snippet(event, '$row_id', $line_number, 'file://$filename');\">&#x25A0;</div>" .  
                    "<div id=\"c_$row_id\" style=\"display:none;\">$source_context</div>");
            }
                    
            fwrite($handle,     
                    "<a style=\"margin-right: 12px;\" href=\"file://$filename\" target=\"_blank\" title=\"$filename\">$filename_base$line_number_field</a>" .
                    "</td>");
                
            fwrite($handle, "<td nowrap class=\"message $source_code_class\" title=\"" . htmlentities(trim($source)) . "\">" .
                            htmlentities( ($result->is_source_code ? ellipsize(trim($source), 50, "...", false) : trim($source)) ) . "</td>");
        }
        if ($num_results_written == 0) {
            fwrite($handle, "<tr><td colspan=\"4\" align=\"center\">No results were found.</td></tr>");
        }
        
        fwrite($handle, $this->get_postamble());
        
        if ($this->use_digital_signature) {
            // Sign the file
            fseek($handle, 0);
            $html_content = "";
            while(!feof($handle)) {
                $html_content .= fread($handle, 4096);
            }
            $signature = Yasca::calculate_signature($html_content);
            fseek($handle, 0, SEEK_END);
            fwrite($handle, "<!-- SIGNATURE: [$signature] -->"); 
        }
                
        fclose($handle);
    }
    
    protected function get_preamble() {
        $generation_date = date('Y-m-d H:i:s');
        $version = constant("VERSION");
        $yasca =& Yasca::getInstance();
        $target_list = implode("<BR/>",array_map(function ($target) use ($yasca) {
					return str_replace($yasca->options['dir'], "", correct_slashes($target));
				}
				,$yasca->target_list));
        $stylesheet_content = file_get_contents("etc/style.css");
        
        return <<<END
        <html>
            <head>
                <title>Yasca v$version - Report</title>
                <style type="text/css">
                    $stylesheet_content;
                </style>
                <script language="javascript">
                    function on_body_load() {
                        fix_empty_cells();
                        attachColumnSorting();
                    }
                    
                    function fix_empty_cells() {
                        var td_list = document.getElementsByTagName('td');
                        for (var i = 0; i < td_list.length; i++) {
                            if (td_list[i].innerHTML == '')
                                td_list[i].innerHTML = '&nbsp;'
                        }
                    }
                    function set_signature_value() {
                        try {
                            var s = document.body.innerHTML
                            return s.substr(s.length-45, 40);
                        } catch(e) {
                        }
                    }
                    function show_code_snippet(evt, snippet_id, line_number, filename) {
                        evt = (window.event) ? window.event : evt;
                        var s = document.getElementById('code_snippet');
                        if (!s) return;
                        var snippet = document.getElementById('c_' + snippet_id).innerHTML;
                        var e = snippet.split("~~~");
                        var t = '';
                        for (var i=0; i<e.length-1; i++) {
                            t += "<span class=\"line_number\">" + (line_number-3+i) + "</span>";
                            if (i+1 == (Math.floor((1+e.length)/2))) {
                                t += "<span class=\"highlight\">" + e[i] + "</span><br/>";
                            } else {
                                t += e[i] + "<br/>";
                            }
                        }                   
                        // Code Snippet
                        s.innerHTML = t;
                        var ps = getPageSize();
                        s.style.left = mouseX(evt);
                        if (parseInt(s.style.left) + 575 > ps[2]) {
                            s.style.left = (parseInt(ps[2]) - 595) + "px";
                        }
                        s.style.top = mouseY(evt);
                        s.style.display = 'block';
                        evt.cancelBubble = true;
                        evt.returnValue = false;
                        
                    }
                    function hide_code_snippet() {
                        document.getElementById('code_snippet').style.display = 'none';
                    }

                    function mouseX(evt) {if (evt.pageX) return evt.pageX; else if (evt.clientX)return evt.clientX + (document.documentElement.scrollLeft ?  document.documentElement.scrollLeft : document.body.scrollLeft); else return null;}

                    function mouseY(evt) {if (evt.pageY) return evt.pageY; else if (evt.clientY)return evt.clientY + (document.documentElement.scrollTop ? document.documentElement.scrollTop : document.body.scrollTop); else return null;}

                    function getPageSize(){
                        var xScroll, yScroll;
                        if (window.innerHeight && window.scrollMaxY) {  
                            xScroll = document.body.scrollWidth;
                            yScroll = window.innerHeight + window.scrollMaxY;
                        } else if (document.body.scrollHeight > document.body.offsetHeight){ // all but Explorer Mac
                            xScroll = document.body.scrollWidth;
                            yScroll = document.body.scrollHeight;
                        } else { // Explorer Mac...would also work in Explorer 6 Strict, Mozilla and Safari
                            xScroll = document.body.offsetWidth;
                            yScroll = document.body.offsetHeight;
                        }
                        
                        var windowWidth, windowHeight;
                        if (self.innerHeight) { // all except Explorer
                            windowWidth = self.innerWidth;
                            windowHeight = self.innerHeight;
                        } else if (document.documentElement && document.documentElement.clientHeight) { // Explorer 6 Strict Mode
                            windowWidth = document.documentElement.clientWidth;
                            windowHeight = document.documentElement.clientHeight;
                        } else if (document.body) { // other Explorers
                            windowWidth = document.body.clientWidth;
                            windowHeight = document.body.clientHeight;
                        }   
                        
                        // for small pages with total height less then height of the viewport
                        if(yScroll < windowHeight){
                            pageHeight = windowHeight;
                        } else { 
                            pageHeight = yScroll;
                        }
                    
                        // for small pages with total width less then width of the viewport
                        if(xScroll < windowWidth){  
                            pageWidth = windowWidth;
                        } else {
                            pageWidth = xScroll;
                        }
                    
                    
                        arrayPageSize = new Array(pageWidth,pageHeight,windowWidth,windowHeight) 
                        return arrayPageSize;
                    }
                    
                    function cancel_event(evt) {
                        evt = (window.event) ? window.event : evt;
                        if (!evt) return true;
                        if (evt.preventDefault) evt.preventDefault();
                        evt.cancelBubble = true;
                        evt.returnValue = false;
                        return true;
                    }       
                    
                    function attachColumnSorting() {    
                        var tags = document.getElementsByTagName("table");
                        for (var i=0; i<tags.length; i++) {
                            var cn = tags[i].attributes;
                            for (var j=0; j<cn.length; j++) {
                                if (cn[j].name == 'sortable' && cn[j].value == 'true') {
                                    try {
                                        tags[i].addEventListener('click', sortColumn, false);
                                    } catch(e) {
                                        tags[i].attachEvent('onclick', sortColumn);
                                    }
                
                                    // Use pointer cursor for the top row
                                    try {
                                        var obj = tags[i].tHead ? tags[i].tHead : tags[i].rows[0];
                                        obj.style.cursor = ie5 ? 'hand' : 'pointer';
                                    } catch(e) {
                                    throw e;
                                    }
                                }
                            }
                        }
                    }
                        
                    function attach_event(evt, event_name, callback) {
                        try { 
                            evt.addEventListener(event_name, callback, false);
                        } catch(e) {
                            try {
                                evt.attachEvent("on" + event_name, callback);
                            } catch(e2) {
                            }
                        }
                    }
                                    
                    var dom = (document.getElementsByTagName) ? true : false;
                    var ie5 = (document.getElementsByTagName && document.all) ? true : false;
                    var arrowUp, arrowDown;
                    
                    if (ie5 || dom)
                        initSortTable();
                    
                    function initSortTable() {
                        arrowUp = document.createElement("SPAN");
                        arrowUp.style.display = 'none';
                        if (ie5) {
                            var tn = document.createTextNode("5");
                            arrowUp.appendChild(tn);
                            arrowUp.className = "arrow";
                    
                            arrowDown = document.createElement("SPAN");
                            arrowDown.style.display = 'none';
                            var tn = document.createTextNode("6");
                            arrowDown.appendChild(tn);
                            arrowDown.className = "arrow";
                        } else {
                            var tn = document.createElement("img");
                            tn.src = 'images/arrow.up.gif';
                            arrowUp.appendChild(tn);
                            arrowUp.className = "arrow";
                    
                            arrowDown = document.createElement("SPAN");
                            arrowDown.style.display = 'none';
                            var tn2 = document.createElement("img");
                            tn2.src = 'images/arrow.down.gif';
                            arrowDown.appendChild(tn2);
                            arrowDown.className = "arrow";
                        }
                    }

                    function sortTable(tableNode, nCol, bDesc, sType) {
                        var tBody = tableNode.tBodies[0];
                        var trs = tBody.rows;
                        var trl= trs.length;
                        var a = new Array();
                        
                        for (var i = 0; i < trl; i++) {
                            a[i] = trs[i];
                        }
                        
                        var start = new Date;
                        a.sort(compareByColumn(nCol,bDesc,sType));
                        
                        for (var i = 0; i < trl; i++) {
                            tBody.appendChild(a[i]);
                        }
                        
                        // check for onsort
                        if (typeof tableNode.onsort == "string")
                            tableNode.onsort = new Function("", tableNode.onsort);
                        if (typeof tableNode.onsort == "function")
                            tableNode.onsort();
                    }
                    
                    function CaseInsensitiveString(s) {
                        return String(s).toUpperCase();
                    }
                    
                    function parseDate(s) {
                        return Date.parse(s.replace(/\-/g, '/'));
                    }
                    
                    function toNumber(s) {
                        return Number(s.replace(/[^0-9\.]/g, ""));
                    }
                    
                    function compareByColumn(nCol, bDescending, sType) {
                        var c = nCol;
                        var d = bDescending;
                        
                        var fTypeCast = String;
                        
                        if (sType == "Number")
                            fTypeCast = Number;
                        else if (sType == "Date")
                            fTypeCast = parseDate;
                        else if (sType == "CaseInsensitiveString")
                            fTypeCast = CaseInsensitiveString;
                    
                        return function (n1, n2) {
                            if (fTypeCast(getInnerText(n1.cells[c])) < fTypeCast(getInnerText(n2.cells[c])))
                                return d ? -1 : +1;
                            if (fTypeCast(getInnerText(n1.cells[c])) > fTypeCast(getInnerText(n2.cells[c])))
                                return d ? +1 : -1;
                            return 0;
                        };
                    }
                    
                    function sortColumnWithHold(e) {
                        // find table element
                        var el = ie5 ? e.srcElement : e.target;
                        var table = getParent(el, "TABLE");
                        
                        // backup old cursor and onclick
                        var oldCursor = table.style.cursor;
                        var oldClick = table.onclick;
                        
                        // change cursor and onclick    
                        table.style.cursor = "wait";
                        table.onclick = null;
                        
                        // the event object is destroyed after this thread but we only need
                        // the srcElement and/or the target
                        var fakeEvent = {srcElement : e.srcElement, target : e.target};
                        
                        // call sortColumn in a new thread to allow the ui thread to be updated
                        // with the cursor/onclick
                        window.setTimeout(function () {
                            sortColumn(fakeEvent);
                            // once done resore cursor and onclick
                            table.style.cursor = oldCursor;
                            table.onclick = oldClick;
                        }, 100);
                    }
                    
                    function sortColumn(e) {
                        var tmp = e.target ? e.target : e.srcElement;
                        var tHeadParent = getParent(tmp, "THEAD");
                        var el = getParent(tmp, "TD");
                        if (el == null) el = getParent(tmp, "TH");
                    
                        if (tHeadParent == null)
                            return;
                    
                        if (el != null) {
                            var p = el.parentNode;
                    
                            var i;
                    
                            // typecast to Boolean
                            el._descending = !Boolean(el._descending);
                    
                            if (tHeadParent.arrow != null) {
                                if (tHeadParent.arrow.parentNode != el) {
                                    tHeadParent.arrow.parentNode._descending = null;    //reset sort order      
                                }
                                tHeadParent.arrow.parentNode.removeChild(tHeadParent.arrow);
                            }
                    
                            if (el._descending)
                                tHeadParent.arrow = arrowUp.cloneNode(true);
                            else
                                tHeadParent.arrow = arrowDown.cloneNode(true);
                    
                            el.appendChild(tHeadParent.arrow);
                    
                                
                    
                            // get the index of the td
                            var cells = p.cells;
                            var l = cells ? cells.length : 0;
                            for (i = 0; i < l; i++) {
                                if (cells[i] == el) break;
                            }
                            
                            if (!cells) {
                                var j=0;
                                for (i=0; i<p.childNodes.length; i++) {
                                    if (p.childNodes[i].nodeName.match(/TH|TD/)) {
                                        if (p.childNodes[i] == el) break;
                                        else j++;
                                    }
                                }
                                i=j;
                            }
                            
                            var table = getParent(el, "TABLE");
                            // can't fail
                            
                            sortTable(table,i,el._descending, el.getAttribute("type"));
                        }
                    }
                    
                    
                    function getInnerText(el) {
                        if (ie5) return el.innerText;   //Not needed but it is faster
                        
                        var str = "";
                        
                        var cs = el.childNodes;
                        var l = cs.length;
                        for (var i = 0; i < l; i++) {
                            switch (cs[i].nodeType) {
                                case 1: //ELEMENT_NODE
                                    if (cs[i].tagName == "INPUT" &&
                                        cs[i].getAttribute("type").toLowerCase() == "text") {
                                        str += cs[i].value;
                                    } else {
                                        str += getInnerText(cs[i]);
                                    }
                                    break;
                                case 3: //TEXT_NODE
                                    str += cs[i].nodeValue;
                                    break;
                            }
                            
                        }
                        
                        
                        return str;
                    }
                    
                    function getParent(el, pTagName) {
                        if (el == null) return null;
                        else if (el.nodeType == 1 && el.tagName.toLowerCase() == pTagName.toLowerCase())    // Gecko bug, supposed to be uppercase
                            return el;
                        else
                            return getParent(el.parentNode, pTagName);
                    }
                    function toggle_scanned_files() {
                        var elt = document.getElementById('target_list');
                        if (!elt) return;
                        if (elt.style.display != 'block') {
                            elt.style.display = 'block';
                        } else {
                            elt.style.display = 'none';
                        }
                    }       
                </script>
            </head>

            
            <body onload="on_body_load();" onclick="hide_code_snippet();">
              <div id="code_snippet" onclick="cancel_event(event);"></div>
              <table class="header_table" cellspacing="0" cellpadding="0">
                <tr>
                    <td class="header_title" nowrap>Yasca</td>
                    <td style="width: 100%;">
                    <table style="border:0;">
                        <tr><td class="header_left" nowrap>Version:</td><td class="header_right">$version [ <a href="#">check for updates</a> ]</td></tr>
                        <tr><td class="header_left" nowrap>Report Generated:</td><td class="header_right">$generation_date</td></tr>
                        <tr><td class="header_left" nowrap>Options:</td><td class="header_right">
                        [ <a href="javascript:void(0);" onclick="toggle_scanned_files();">view/hide scanned files</a> | <a href="http://yasca.sourceforge.net" target="_blank">user guide</a> | <a href="mailto:scovetta@users.sourceforge.net">feedback</a> ]</td></tr>
                    </table>
                    </td>
                </tr>
              </table>
              <div id="target_list">
                <b>Scanned Files:</b><br/>
                $target_list
              </div>
              <div id="results">
                <table cellpadding="0" border="0" cellspacing="2" sortable="true">
                    <thead>
                        <th nowrap>#</th>
                        <th nowrap>Category</th>
                        <th nowrap>Location</th>
                        <th nowrap>Message / Source Line</th>
                    </thead>
END;
        }
        
        protected function get_postamble() {
            return <<<END
                </table>
              </div>
            </body>
        </html>
END;
        }   
}



?>
