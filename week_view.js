

function page_open() {
    /**
     * Generates time column, shows time line, and shows current week for when the page is loaded
     */
    generate_times();
    calc_and_show_cur_time();
    jump_today();
}


function jump_today() {
    /**
     * Resets week offset to 0 so that the current week is shown
     */
    week_offset = 0;
    new_week(0);
}

async function new_week(offset_change) {
    /**
     * Function used to update week whenever one of the buttons at the top of the page are pressed
     */
    week_offset += offset_change;

    let week_items = await get_week_items();
    if (!week_items) return;
    let week_events = week_items[0];
    let week_tasks = week_items[1];
    let week_assignments = week_items[2];

    remove_events();
    update_tasks(week_events);
    update_tasks(week_tasks);
    update_tasks(week_assignments);


    handle_collisions(check_collisions(week_events.concat(week_tasks).concat(week_assignments)));

    remove_date_bar();
    set_date_bar();
    find_month();
    cur_time_shown();
}

async function get_week_items() {
    /**
     * Uses AJAX approach to query all items necessary in a given week - reducing load of information on browser when querying the entire database on page load
     * Asynchronous function so that await command can be used, so data only attempted to be shown when it is loaded
     */
    let week_start = get_start_of_week();

    let week_end = new Date(week_start);
    week_end.setDate(week_start.getDate() + 6);
    week_end.setHours(23, 59, 59);

    try {
        let response = await fetch(`get_week_items.php?start=${encodeURIComponent(dt_to_SQL_format(week_start))}&end=${encodeURIComponent(dt_to_SQL_format(week_end))}`)
        let data = await response.json();

        if (data.error) {
            console.error("Server Error:", data.error);
            return;
        }

        /* Uses forEach functions on the returned arrays so that Dates objects are only created once for each event
           Also changes some dictionary keys for ease of processing later in the program */

        let week_events = data.events;
        week_events.forEach(event => {
            event.start_time = new Date(event.start_time.replace(' ', 'T'));
            event.tskType = 'event';
        });

        let week_tasks = data.toDo;
        week_tasks.forEach(task => {
            // the deadline is when it ends, we must subtract duration to find when it starts
            let end_time = new Date(task.deadline.replace(' ', 'T'));
            let duration_mins = parseInt(task.duration) || 30; // Default to 30 mins just in case
            
            task.start_time = new Date(end_time.getTime() - (duration_mins * 60000));
            task.tskType = 'task';
            delete task.deadline;
        });

        let week_assignments = data.assignments;
        week_assignments.forEach(assignment => {
            assignment.start_time = new Date(assignment.due_date.replace(' ', 'T'));
            assignment.duration = 20;
            assignment.tskType = 'assignment';
            delete assignment.due_date;
        })

        return [week_events, week_tasks, week_assignments];
    }
    catch (err) {
        console.error("AJAX request failed: ", err);
        return;
    }
}

function get_start_of_week() {
    /**
     * Gets the start date of a given week
     */
    let target_date = new Date();
    target_date.setDate(target_date.getDate() + week_offset * 7);
    let day_of_target = target_date.getDay();

    let week_start = new Date(target_date);
    week_start.setDate(target_date.getDate() - day_of_target);
    week_start.setHours(0, 0, 0);
    return week_start;
}

function remove_events() {
    /**
     * Removes all of the events on the calendar so that new ones can be shown instead
     */
    document.querySelectorAll('.event_block, .extended_block').forEach(event => event.remove());
}

function update_tasks(events) {
    /**
     * Creates an event block and an extended block for each element in a given week and appends them to their respective column
     * Handles events from different tables in separate blocks
     */
    for (var i = 0; i < events.length; i++) {
        let event = events[i];

        let event_dur = parseInt(event.duration);
        let event_time = event.start_time;

        let event_day = event_time.getDay();
        let event_hour = event_time.getHours();
        let event_min = event_time.getMinutes();

        let mins_into_day = event_hour * 60 + event_min;

        let day_col = document.getElementById(String(event_day));

        // For the standard (unselected) event blocks
        let new_event_div = document.createElement('div');
        new_event_div.className = "event_block ";
        if (event.tskType === 'event') {
            new_event_div.className += event.type.toUpperCase();
            new_event_div.id = "ev" + event.EventID;
            new_event_div.textContent = event.module;
        }
        else if (event.tskType === 'task') {
            new_event_div.id = "tk" + event.taskID;
            new_event_div.textContent = event.title;
            new_event_div.style.backgroundColor = `color-mix(in srgb , ${event.color || '#808080'} 30%, white 70%)`;
            new_event_div.style.borderColor = `color-mix(in srgb , ${event.color || '#808080'} 20%, white 80%)`;
            new_event_div.style.borderLeftColor = event.color || '#808080';
        }
        else if (event.tskType === 'assignment') {
            new_event_div.className += 'assignment';
            new_event_div.id = "as" + event.AssignmentID;
            new_event_div.textContent = event.title;
        }

        new_event_div.style.top = mins_into_day + "px"
        new_event_div.style.height = event_dur + "px"
        new_event_div.addEventListener('click', function() {show_extended(new_event_div.id)});

        day_col.appendChild(new_event_div);

        //For extended (selected) event blocks
        let extended_event_div = document.createElement('div');

        let min_hour = {hour: '2-digit', minute: '2-digit'};
        let end_time = new Date((event_time.getTime() + event_dur * 60000));
        let start_t = event_time.toLocaleTimeString([], min_hour);
        let end_t = end_time.toLocaleTimeString([], min_hour);


        extended_event_div.className = "extended_block ";
        if (event.tskType === 'event') {
            extended_event_div.className += event.type.toUpperCase();
            extended_event_div.id = "eev" + event.EventID;        
            extended_event_div.innerHTML = `
            <div class="ext_header">
                <h3>${escapeHTML(event.module)}</h3>
                <button type="button" class="close_button" onclick="hide_extended('${escapeHTML(extended_event_div.id)}')">&times;</button>
            </div>
            <div class="ext_body">
                <p><strong>Type: </strong>${escapeHTML(event.type)}</p>
                <p><strong>Time: </strong>${escapeHTML(start_t)} - ${escapeHTML(end_t)}</p>
                <p><strong>Location: </strong>${escapeHTML(event.location || 'TBC')}</p>
                <p><strong>Staff: </strong>${escapeHTML(event.staff || 'TBC')}</p>
            </div>`
        }
        else if (event.tskType === 'task') {
            extended_event_div.id = "etk" + event.taskID;
            extended_event_div.innerHTML = `
            <div class="ext_header">
                <h3>${escapeHTML(event.title)}</h3>
                <div class="ext_buttons_container">
                    <form method="POST" action="delete_todo.php" onsubmit="return confirm('Are you sure you want to permanently delete this task?')";>
                        <input type="hidden" name="taskID" value=${event.taskID}>
                        <input type="hidden" name="source_page" value="week_view.php">
                        <button type="submit" class="ext_buttons" id="del_button" title="Delete">🗑️</button>
                    </form>
                    <button type="button" class="ext_buttons" id="close_button" onclick="hide_extended('${escapeHTML(extended_event_div.id)}')">&times;</button>
                </div>
            </div>
            <div class="ext_body">
                <p><strong>Type: </strong>${escapeHTML(event.type)}</p>
                <p><strong>Description: </strong>${escapeHTML(event.description || 'N/A')}</p>
                <p><strong>Time: </strong>${escapeHTML(start_t)} - ${escapeHTML(end_t)}</p>
            </div>`
            extended_event_div.style.backgroundColor = `color-mix(in srgb , ${event.color || '#808080'} 30%, white 70%)`;
            extended_event_div.style.borderColor = `color-mix(in srgb , ${event.color || '#808080'} 20%, white 80%)`;
            extended_event_div.style.borderLeftColor = event.color || '#808080';
        }
        else if (event.tskType === 'assignment') {
            extended_event_div.className += 'assignment';
            extended_event_div.id = 'eas' + event.AssignmentID;
            extended_event_div.innerHTML = `
            <div class="ext_header">
                <h3>${escapeHTML(event.title)}</h3>
                <button type="button" class="close_button" onclick="hide_extended('${escapeHTML(extended_event_div.id)}')">&times;</button>
            </div>
            <div class="ext_body">
                <p><strong>Deadline: </strong>${escapeHTML(start_t)}</p>
                <p><strong>Description: </strong>N/A</p>
            </div>`
        }

        extended_event_div.style.visibility = "hidden";
        document.body.appendChild(extended_event_div);
    }
}
 

function check_collisions(events) {
    /**
     * Iterates through all the events being shown and notes any event groups that overlap
     */
    let colliding_clusters = [];
    let cur_cluster = [];
    let cur_cluster_end = 0;

    events.sort((a, b) => a.start_time - b.start_time);

    for (var i = 0; i < events.length; i++) {

        let ev_s = events[i].start_time.getTime();
        let ev_e = ev_s + events[i].duration * 60000;

        // if no current collision
        if (cur_cluster.length === 0) {
            cur_cluster.push(events[i]);
            cur_cluster_end = ev_e;
        }
        else {
            // Is there is a collision?
            if (ev_s < cur_cluster_end) {
                cur_cluster.push(events[i]);

                if (ev_e > cur_cluster_end) {
                    cur_cluster_end = ev_e;
                }
            }
            else {
                colliding_clusters.push(cur_cluster);
                cur_cluster = [events[i]];
                cur_cluster_end = ev_e;
            }
        }
    }
    if (cur_cluster.length > 0) colliding_clusters.push(cur_cluster);
    return colliding_clusters;
}

function handle_collisions(collisions_arr) {
    /**
     * Takes any colliding items as a parameter and creates lanes based on the number of overlapping events at one time
     * Once the maximum number of lanes is calculated, it calculates the width of the elements needed to show them all, and uses the lane number to find the indent the event needs
     */
    for (var i = 0; i < collisions_arr.length; i++) {
        let no_colliding = collisions_arr[i].length;
        let lanes = [];

        for (var j = 0; j < no_colliding; j++) {
            let event = collisions_arr[i][j];
            let ev_start = event.start_time;
            let ev_height = new Date(ev_start.getTime() + event.duration * 60000);
            let ev_lane = -1;

            for (var k = 0; k < lanes.length; k++) {
                if (ev_start >= lanes[k]) {
                    ev_lane = k;
                    break;
                }
            }
            if (ev_lane === -1) {
                ev_lane = lanes.length;
                lanes.push(ev_height);
            }
            else {
                lanes[ev_lane] = ev_height;
            }
            event.lane = ev_lane;
        }

        let total_lanes = lanes.length;
        let ev_widths = 97.5 / total_lanes;
        for (var l = 0; l < collisions_arr[i].length; l++) {
            let ev = collisions_arr[i][l];
            if (ev.tskType === 'event') ev_id = 'ev' + ev.EventID;
            else if (ev.tskType === 'task') ev_id = 'tk' + ev.taskID;
            else ev_id = 'as' + ev.AssignmentID;
            let ev_block = document.getElementById(ev_id);
            if (ev_block) {
                ev_block.style.width = ev_widths + "%";
                ev_block.style.left = (1.25 + (ev.lane * ev_widths)) + "%";
            }
        }
    }
}

function remove_date_bar() {
    /**
     * Deletes all of the dates for the current week so that new ones can be put in their place
     */
    let day_container = document.getElementById('-1')
    while (day_container.children.length > 1) {
        day_container.removeChild(day_container.children[1]);
    }
}

function set_date_bar() {
    /**
     * Shows the days of the week with their respective date at the top of the calendar section
     * Highlights the current day if week_offset is 0
     */
    let this_week_s = get_start_of_week();
    let days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    let titles_div = document.getElementById('-1');

    for (var i = 0; i < days.length; i++) {
        let name = days[i];
        let name_div = document.createElement('div');
        name_div.className = "day_name";
        name_div.id = "d" + i;
        name_div.innerHTML = name + "<br>" + this_week_s.getDate();
        this_week_s.setDate(this_week_s.getDate() + 1);
        titles_div.appendChild(name_div);
    }

    if (week_offset === 0) {
        let today = new Date();
        let day = document.getElementById('d' + today.getDay());
        day.className += ' today'
    }
}

function find_month() {
    /**
     * Finds month of the first day of the week
     */
    let week_start = get_start_of_week();
    let month_container = document.getElementById('month');
    month_container.textContent = week_start.toLocaleString('default', {month: 'long'});
}

function cur_time_shown() {
    /**
     * Determines if the current week is being shown in the week view, then shows the time line if it is said week (otherwise hides it)
     */
    let time_line = document.getElementById("cur_time");
    let marker1 = document.getElementById('marker1');
    let marker2 = document.getElementById('marker2');
    if (time_line !== null) {
        if (week_offset === 0) {
            time_line.style.visibility = "visible";
            marker1.style.visibility = "visible";
            marker2.style.visibility = "visible";
            time_line.style.top = get_line_height()[0] + "px";
            marker1.style.top = (get_line_height()[0]) + "px";
            marker2.style.top = (get_line_height()[0]) + "px";
        }
        else {
            time_line.style.visibility = "hidden";
            marker1.style.visibility = "hidden";
            marker2.style.visibility = "hidden";
        }
    }
}

function get_line_height() {
    /**
     * Calculates current time and the line offset down the day
     */
    let date_now = new Date();
    let today = date_now.getDay() + 1;
    return [date_now.getHours() * 60 + date_now.getMinutes(), today];
}

function calc_and_show_cur_time() {
    /**
     * Uses current time to create a horizontal rule element to show time with circles to show the day
     */
    let time_data = get_line_height();
    let line_height = time_data[0];
    let today = time_data[1];

    let week_box = document.querySelector('#WeekID');

    // Finds the flex size of the members of members of the week view, where the time line spans
    let time_col_flex = parseFloat(window.getComputedStyle(document.querySelector('.times')).flexGrow);
    let day_col_flex = parseFloat(window.getComputedStyle(document.querySelector('.days')).flexGrow);

    let time_line = document.createElement('hr');
    let marker1 = document.createElement('div');
    let marker2 = document.createElement('div');

    time_line.className = "cur_time_line";
    marker1.className = "time_marker";
    marker2.className = "time_marker";

    time_line.id = "cur_time";
    marker1.id = "marker1";
    marker2.id = "marker2";

    time_line.style.top = line_height + "px";
    marker1.style.top = line_height + "px";
    marker2.style.top = line_height + "px";

    let total_width = time_col_flex + (document.getElementsByClassName('days').length) * day_col_flex;
    marker1.style.left = 100/total_width * (time_col_flex + day_col_flex * (today - 1)) + "%";
    marker2.style.left = 100/total_width * (time_col_flex + day_col_flex * today) + "%";


    week_box.appendChild(marker1);
    week_box.appendChild(marker2);
    week_box.appendChild(time_line);

    // Scrolls the page to the current time
    let cal_box = document.querySelector('.calendarContainer');
    cal_box.scrollTo(0, ((line_height > 60)? line_height - 60: line_height));
}

function generate_times() {
    // Fills out the time column of the calendar with the times of day
    let time_col = document.getElementById('7');
    for (var i = 0; i < 24; i++) {
        let time = document.createElement('div')
        time.className = "time";
        time.textContent = i > 9 ? i + ":00": "0" + i + ":00";
        time_col.appendChild(time);
}
}

function show_extended(id) {
    /**
     *  Sets all event blocks to hidden, then shows the selected event block - blurs background
     */
    let extended_boxes = document.getElementsByClassName('extended_block');
    for (var i = 0; i < extended_boxes.length; i++) {
        extended_boxes[i].style.visibility = "hidden";
    }
    let extended_box  = document.getElementById('e' + id);
    extended_box.style.visibility = "visible";
    document.querySelector('.calendarContainer').style.filter = "blur(10px)";
}

function hide_extended(id) {
    /** 
     * Hides the selected extended event block amd unblurs the background
     */
    let extended_box = document.getElementById(id);
    extended_box.style.visibility = "hidden";
    document.querySelector('.calendarContainer').style.filter = "blur(0px)";
}

function escapeHTML(text) {
    /** 
     * Makes sure that any html tags entered in a string is not parsed when added to the script using innerHTML
     */
    let map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    }
    return String(text).replace(/[&<>"']/g, function(m) { return map[m];});
}

function dt_to_SQL_format(date) {
    // MySQL doesn't accept .js date object, so need to convert to ISO format
    const pad = (num) => num.toString().padStart(2, '0');
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}:${pad(date.getSeconds())}`;
}

// Calles page_open() when at the earliest possible opportunity (before CSS loaded)
document.addEventListener('DOMContentLoaded', function() {

    // 1. Now that the HTML is loaded, it's safe to attach the buttons!
    document.getElementById('month').addEventListener('click', function() {document.getElementById('date_selector').showPicker()})
    document.getElementById('date_selector').addEventListener('change', function() {
        let selected_date = new Date(this.value);
        let date_delta = selected_date.getTime() - new Date().getTime();
        let diff_days = date_delta / (1000 * 3600 * 24);
        console.log(diff_days / 7);
        week_offset = Math.round(diff_days / 7);
        week_offset = (selected_date.getDay() === 0)? week_offset + 1: week_offset;
        console.log(week_offset);
        new_week(0);
    })
    document.getElementById('next_week').addEventListener('click', function() {new_week(1)});
    document.getElementById('today').addEventListener('click', function() {jump_today()});
    document.getElementById('prev_week').addEventListener('click', function() {new_week(-1)});
    
    // 2. Start drawing the calendar
    page_open();
});