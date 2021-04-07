/*
    This file is part of Moodle - http://moodle.org/

    Moodle is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    Moodle is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with Moodle.  If not, see <http://www.gnu.org/licenses/>.
*/

/**
 * Class containing data for createusers block.
 *
 * @package    block_createusers
 * @copyright  tim st.clair <https://github.com/frumbert>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery'], function($) {
	var t = {
		init: function(ref) {
			ref.on("submit", t.submit);
			t.listen(ref);
			t.create(ref);
		},
		listen: function(r) {
			r.focusin(function(e) {
				var tr = $(e.target).closest("tr"),
					tb = tr.closest("tbody"),
					len = tb.children("tr").length,
					pos = tr.index(),
					hasData = false;

				// determine if we have any data on this row
				$("input", tr).each(function() { hasData = this.value.trim().length || hasData});

				// add new row if it is the last row and something in this row has a value
				if (pos === len -1 && hasData) {
					var newtr = tr.clone(true);
					tb.append(newtr);
					newtr.find("input").each(function() { this.value = ''; });
					$("button", r).removeAttr("disabled"); // also release submit button
				}
			});

		},
		create: function(r) {
			$("button", r).addClass("btn-primary"); // we know if this js is working as the button gets a new classname
		},
		submit: function(e) {
			console.dir(e);
			$tgt = $(e.target);
			$.post($tgt.attr("action"), $tgt.serialize())
			.done(function(result) {
				$tgt.trigger("reset"); // return form to initial state
				$("tbody>tr", $tgt).slice(1).remove(); // return to a single row
			})
			.fail(function(message) {
				console.warn(message); // probably a bad sesskey
			});
			return false; // don't submit via html 
		}
	}
	return t;
})