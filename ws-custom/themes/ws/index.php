<?php
// The Homepage template
// @package WS
// @subpackage Localbiz Child
// @since WS 1.0
$GLOBALS['ws_html_attributes']['html']['class'][] = 'home';
global $ws_headings, $ws_content, $locations, $longDateTime;
$ws_contents_url = ws_contents_url();
$ws_content_root_url = ws_content_root_url();
$workLocations = $locations->workLocation;
if(count($workLocations) == 1){
	$GLOBALS['google_place_id'] = $workLocations[0]->google_place_id;
	global $google_place_id;
}
include_template('template-parts/header');
?>
<nav>
	<button title="Load file.svg">Load</button>
	<button title="Save file.svg">Save</button>
	<button title="Clear SVG code">Reset</button>
</nav>
<nav>
	<button title="Line">Line</button>
	<button title="Rectangle">Rectangle</button>
	<button title="Circle">Circle</button>
	<button title="Arc">Arc</button>
	<button title="Sin">Sin</button>
</nav>
<nav>
	<button title="Pi Greek">π</button>
	<button title="Phi">Phi</button>
</nav>
<svg id="svg_canvas" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"
	width="100%" height="100%"
	viewBox="-7.5 -1.5 15 3"
	preserveAspectRatio="xMidYMid slice">
    <g id="axes" stroke="black" stroke-width=".01" >
        <line x1="-100" y1="0" x2="100" y2="0" />
        <line x1="0" y1="-100" x2="0" y2="100" />
    </g>
    <g id="sines" fill="none" stroke-width=".02" >
    </g>
</svg>
<section>
	<h1>Tools</h1>
	<form id="svg_position_form">
		<p>
			<label><span>x </span><input name="x" type="number" step="1" value="0" /></label <label><span>x </span><input name="y" type="number" step="1" value="0" /></label><br />
			<label><span>Rotation </span><input name="rotation_value" type="number" step="any" value="0" /><select name="rotation_unit"><option value="deg">deg</option><option value="rad">rad</option><option value="turn">turn</option><option value="grad">grad</option></select></label>
		</p>
	</form>
	<form id="svg_style_form">
		<fieldset>
			<legend>Fill</legend>
			<label><span>Color </span><input name="fill_color" type="color" /></label>
			<label><span>Opacity </span><input name="fill_opacity" type="number" value="1" step="0.10" min="0" max="1" /></label><br />
		</fieldset>
		<fieldset>
			<legend>Stroke</legend>
			<label><span>Color </span><input name="stroke_color" type="color" /></label>
			<label><span>Opacity </span><input name="stroke_opacity" type="number" value="1" step="0.10" min="0" max="1" /></label><br />
			<label><span>Width </span><input name="stroke_width_value" value="0" /></label><select name="stroke_width_unit"><option value="px">px</option><option value="em">em</option></select><br />
		</fieldset>
	</form>
	<form id="golden_ratio_form">
		<fieldset>
			<legend>Golden ratio</legend>
			<p>Split a segment in two parts</p>
			<p>
				<label><span>Segment lenght </span><input name="segment_lenght" type="number" value="1000" onchange="update_golden_section(this.value, false, false);" /></label><br />
				<label><span>Long part </span><input name="long_part" type="number" step="any" onchange="update_golden_section(false, this.value, false);" /></label><br />
				<label><span>Short part </span><input name="short_part" type="number" step="any" onchange="update_golden_section(false, false, this.value);" /></label>
			</p>
		</fieldset>
	</form>
	<form id="golden_rectangle_form">
		<fieldset>
			<legend>Golden rectangle</legend>
			<p>
				Draw an SVG Golden rectangle. <br />
				Decimal values rounded in pixel values.
			</p>
			<p>
				<label><span>Long side </span><input name="long_side" type="number" step="any" value="161.8" onchange="update_golden_rectangle(this.value, false);" /></label> <label><input name="long_side_px" type="number" disabled="disabled" /> px</label><br />
				<label><span>Short side </span><input name="short_side" type="number" step="any" onchange="update_golden_rectangle(false, this.value);" /></label> <label><input name="short_side_px" type="number" disabled="disabled" /> px</label><br />
			</p>
			<p><button type="button" onclick="add_svg_golden_rectangle();">Add Golden Rectangle</button></p>
		</fieldset>
	</form>
</section>
<style type="text/css">
#svg_canvas {
width: 1000px;
height: 500px;
}
</style>
<script type="text/javascript">
var phi = (Math.pow(5, 1/2))/2 + 1/2;
console.log("phi = " + phi + ";");
function update_golden_section(segment_lenght, long_part, short_part) {
	if(segment_lenght == false){ document.getElementById("golden_ratio_form").segment_lenght.value = ''; }
	if(long_part == false){ document.getElementById("golden_ratio_form").long_part.value = ''; }
	if(short_part == false){ document.getElementById("golden_ratio_form").short_part.value = ''; }
	var golden_section = calc_golden_section(segment_lenght, long_part, short_part);
	document.getElementById("golden_ratio_form").segment_lenght.value = golden_section.segment_lenght;
	document.getElementById("golden_ratio_form").long_part.value = golden_section.long_part;
	document.getElementById("golden_ratio_form").short_part.value = golden_section.short_part;
}
function calc_golden_section(segment_lenght, long_part, short_part) {
	var golden_section = {};
	if(segment_lenght > 0 && !long_part && !short_part){
		Object.defineProperty(golden_section, 'segment_lenght', {
		  value: segment_lenght*1,
		  writable: true
		});
		Object.defineProperty(golden_section, 'long_part', {
		  value: golden_section.segment_lenght / phi,
		  writable: true
		});
		Object.defineProperty(golden_section, 'short_part', {
		  value: golden_section.segment_lenght - golden_section.long_part,
		  writable: true
		});
		Object.defineProperty(golden_section, 'segment_lenght_px', {
		  value: Math.round(segment_lenght*1),
		  writable: true
		});
		Object.defineProperty(golden_section, 'long_part_px', {
		  value: Math.round(golden_section.segment_lenght_px / phi),
		  writable: true
		});
		Object.defineProperty(golden_section, 'short_part_px', {
		  value: golden_section.segment_lenght_px - golden_section.long_part_px,
		  writable: true
		});
	} else if(!segment_lenght && long_part > 0 && !short_part){
		Object.defineProperty(golden_section, 'long_part', {
			value: long_part*1,
			writable: true
		});
		Object.defineProperty(golden_section, 'segment_lenght', {
			value: golden_section.long_part * phi,
			writable: true
		});
		Object.defineProperty(golden_section, 'short_part', {
			value: golden_section.segment_lenght - golden_section.long_part,
			writable: true
		});
		Object.defineProperty(golden_section, 'long_part_px', {
			value: Math.round(long_part*1),
			writable: true
		});
		Object.defineProperty(golden_section, 'segment_lenght_px', {
			value: Math.round(golden_section.long_part_px * phi),
			writable: true
		});
		Object.defineProperty(golden_section, 'short_part_px', {
			value: golden_section.segment_lenght_px - golden_section.long_part_px,
			writable: true
		});
	} else if(!segment_lenght && !long_part && short_part > 0){
		Object.defineProperty(golden_section, 'short_part', {
			value: short_part*1,
			writable: true
		});
		Object.defineProperty(golden_section, 'long_part', {
			value: golden_section.short_part * phi,
			writable: true
		});
		Object.defineProperty(golden_section, 'segment_lenght', {
			value: golden_section.long_part + golden_section.short_part,
			writable: true
		});
		Object.defineProperty(golden_section, 'short_part_px', {
			value: Math.round(short_part*1),
			writable: true
		});
		Object.defineProperty(golden_section, 'long_part_px', {
			value: Math.round(golden_section.short_part_px * phi),
			writable: true
		});
		Object.defineProperty(golden_section, 'segment_lenght_px', {
			value: golden_section.long_part + golden_section.short_part,
			writable: true
		});
	}
	console.log(golden_section);
	return golden_section;
}
function update_golden_rectangle(long_side, short_side){
	if(long_side == false){ document.getElementById("golden_rectangle_form").long_side.value = ''; }
	if(short_side == false){ document.getElementById("golden_rectangle_form").short_side.value = ''; }
	var golden_rectangle = calc_golden_rectangle(long_side, short_side);
	document.getElementById("golden_rectangle_form").long_side.value = golden_rectangle.long_side;
	document.getElementById("golden_rectangle_form").short_side.value = golden_rectangle.short_side;
	document.getElementById("golden_rectangle_form").long_side_px.value = golden_rectangle.long_side_px;
	document.getElementById("golden_rectangle_form").short_side_px.value = golden_rectangle.short_side_px;
}
function calc_golden_rectangle(long_side, short_side){
	var golden_rectangle = {};
	if(!long_side && !short_side){
		long_side = 1618;
	}
	if(long_side > 0 && !short_side){
		Object.defineProperty(golden_rectangle, 'long_side', {
		  value: long_side,
		  writable: true
		});
		Object.defineProperty(golden_rectangle, 'short_side', {
		  value: golden_rectangle.long_side / phi,
		  writable: true
		});
		Object.defineProperty(golden_rectangle, 'long_side_px', {
		  value: Math.round(long_side),
		  writable: true
		});
		Object.defineProperty(golden_rectangle, 'short_side_px', {
		  value: Math.round(golden_rectangle.short_side),
		  writable: true
		});
	} else if(!long_side && short_side > 0){
		Object.defineProperty(golden_rectangle, 'short_side', {
		  value: short_side,
		  writable: true
		});
		Object.defineProperty(golden_rectangle, 'long_side', {
		  value: golden_rectangle.short_side * phi,
		  writable: true
		});
		Object.defineProperty(golden_rectangle, 'short_side_px', {
		  value: Math.round(short_side),
		  writable: true
		});
		Object.defineProperty(golden_rectangle, 'long_side_px', {
		  value: Math.round(golden_rectangle.long_side),
		  writable: true
		});
	}
	console.log(golden_rectangle);
	return golden_rectangle;
}
const hex2rgba = (hex, alpha = 1) => {
  const [r, g, b] = hex.match(/\w\w/g).map(x => parseInt(x, 16));
  return `rgba(${r},${g},${b},${alpha})`;
};
function create_svg_element(tagName, attributes) {
  tagName = document.createElementNS("http://www.w3.org/2000/svg", tagName);
  for (var p in attributes)
    tagName.setAttributeNS(null, p, attributes[p]);
  return tagName;
}
function add_svg_element(tagName, attributes){
	var svg_element = create_svg_element(tagName, attributes);
	document.getElementById("svg_canvas").appendChild(svg_element);
}
function add_svg_golden_rectangle(){
	var rotation_value = 	document.getElementById("svg_position_form").rotation_value.value;
	var rotation_unit = document.getElementById("svg_position_form").rotation_unit.value;
	var fill = "fill: "+hex2rgba(document.getElementById("svg_style_form").fill_color.value, document.getElementById("svg_style_form").fill_opacity.value)+"; ";
	console.log(fill);
	var stroke = "stroke-width: "+document.getElementById("svg_style_form").stroke_width_value.value+document.getElementById("svg_style_form").stroke_width_unit.value+"; stroke: "+document.getElementById("svg_style_form").stroke_color.value+"; ";
	if(rotation_value){
		var transform = "transform: rotate("+rotation_value+rotation_unit+")";
	}
	add_svg_element("rect", {
		x: document.getElementById("svg_position_form").x.value, y: document.getElementById("svg_position_form").y.value,
		width: document.getElementById("golden_rectangle_form").long_side_px.value,
		height: document.getElementById("golden_rectangle_form").short_side_px.value,
		long_side: document.getElementById("golden_rectangle_form").long_side.value,
		short_side: document.getElementById("golden_rectangle_form").short_side.value,
		style: fill+stroke+transform
	})
}
// SVG Draggable
function makeDraggable(evt) {
	var svg = evt.target;

	svg.addEventListener('mousedown', startDrag);
	svg.addEventListener('mousemove', drag);
	svg.addEventListener('mouseup', endDrag);
	svg.addEventListener('mouseleave', endDrag);
	svg.addEventListener('touchstart', startDrag);
	svg.addEventListener('touchmove', drag);
	svg.addEventListener('touchend', endDrag);
	svg.addEventListener('touchleave', endDrag);
	svg.addEventListener('touchcancel', endDrag);

	function getMousePosition(evt) {
		var CTM = svg.getScreenCTM();
		if (evt.touches) { evt = evt.touches[0]; }
		return {
			x: (evt.clientX - CTM.e) / CTM.a,
			y: (evt.clientY - CTM.f) / CTM.d
		};
	}

	var selectedElement, offset, transform;

	function startDrag(evt) {
		if (evt.target.classList.contains('draggable')) {
			selectedElement = evt.target;
			offset = getMousePosition(evt);

			// Make sure the first transform on the element is a translate transform
			var transforms = selectedElement.transform.baseVal;

			if (transforms.length === 0 || transforms.getItem(0).type !== SVGTransform.SVG_TRANSFORM_TRANSLATE) {
				// Create an transform that translates by (0, 0)
				var translate = svg.createSVGTransform();
				translate.setTranslate(0, 0);
				selectedElement.transform.baseVal.insertItemBefore(translate, 0);
			}

			// Get initial translation
			transform = transforms.getItem(0);
			offset.x -= transform.matrix.e;
			offset.y -= transform.matrix.f;
		}
	}
	function drag(evt) {
		if (selectedElement) {
			evt.preventDefault();
			var coord = getMousePosition(evt);
			transform.setTranslate(coord.x - offset.x, coord.y - offset.y);
		}
	}

	function endDrag(evt) {
		selectedElement = false;
	}
}
// End SVG Draggable
update_golden_section(document.getElementById("golden_ratio_form").segment_lenght.value, false, false);
update_golden_rectangle(document.getElementById("golden_rectangle_form").long_side.value, false);
</script>
<?php
include_template('template-parts/footer');
?>
