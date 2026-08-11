<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Week View</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
        }

        html {
            scrollbar-width: thin;
            scrollbar-color: #264653 #f0efeb;
        }

        html::-webkit-scrollbar {
            width: 12px;
        }

        html::-webkit-scrollbar-track {
            background: #f0efeb;
        }

        html::-webkit-scrollbar-thumb {
            background-color: #264653;
            border-radius: 10px;
            border: 3px solid #f0efeb;
        }

        body {
            background-color: #f4f7f6;
            color: #264653;
            padding: 20px;
            max-width: 100vw;
            margin: 0 auto;
            padding-bottom: 80px;
        }

        h1 {
            text-align: center;
            font-size: 2.3em;
            font-weight: bold;
            color: #333;
        }    

        .header {
            display: flex;
            justify-content: center;
            border-bottom: 2px solid #ddd;
            padding-bottom: 5px;
            margin-bottom: 10px;
        }

        .calendarContainer {
            height: 73.5vh;
            overflow-y: auto;
            overflow-x: hidden;

            border-radius: 8px;
            background-color: #ffffff;

            scrollbar-width: thin;
            scrollbar-color: #264653 #f0efeb;
        }

        .calendarContainer::-webkit-scrollbar {
            width: 12px;
        }

        .calendarContainer::-webkit-scrollbar-track {
            border-radius: 0px 8px 8px 0px;
            background: #f0efeb;
        }

        .calendarContainer::-webkit-scrollbar-thumb {
            background-color: #264653;
            border-radius: 10px;
            border: 3px solid #f0efeb;
        } 

        .week {
            display: flex;
            width: 100%;
            text-align: center;
            position: relative;
        }

        .day_names {
            width: 100%;
            display: flex;
            text-align: center;
            background-color: #f0efeb;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .day_name {
            flex: 7;
            border: 1px solid #d8d2c2;
            overflow: hidden;
        }

        .day_name.today {
            color: #e76f51; 
            font-weight: bold;
        }

        .times, .days {
            position: relative;
            border: 1px solid #a9a9a9;

            background-image: repeating-linear-gradient(
                to bottom,
                transparent,
                transparent 59px,
                #eae6df 59px,
                #eae6df 60px
            );
        }

        .times {
            flex: 3;
            height: 1440px;
            background-color: #f9f8f6;
        }

        .time {
            height: 60px;
            flex: 24;
            overflow: hidden;
        }

        .days {
            height: 1440px;
            flex: 7;
        }

        .event_block {
            position: absolute;
            width: 97.5%;
            left: 1.25%;
            border: 1px solid black;
            border-left-width: 5px;
            border-radius: 5px;
            box-shadow: 0 2px 5px #00000080;
            box-sizing: border-box;

            padding: 0 6px;
            color: #264653;
            background-color: #ebcbe6;
            border-color: #fbf3f6;
            border-left-color: #edaec8;
            font-size: 0.8em;
            overflow: hidden;

            text-align: center;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            text-overflow: ellipsis;

            word-break: break-word;
            hyphens: auto;
        }

        .event_block:hover {
            box-shadow: 0 -1px 3px #00000080;
        }

        .event_block.LECTURE, .extended_block.LECTURE {background-color: #e8f5e9; border-color: #c2dfe3; border-left-color: #2a9d8f;}
        .event_block.ADVISEMENT, .extended_block.ADVISEMENT {background-color: #f4f1de; border-color: #eae6df; border-left-color: #e07a5f;}
        .event_block.LABORATORY, .extended_block.LABORATORY {background-color: #fde2e4; border-color: #fad2e1; border-left-color: #d5bdaf;}
        .event_block.DROP-IN, .extended_block.DROP-IN {background-color: #e0fbfc; border-color: #c2dfe3; border-left-color: #98c1d9;}
        .event_block.EXAMPLES, .extended_block.EXAMPLES {background-color: #ffd6a5; border-color: #ffc685; border-left-color: #f4a261;}
        .event_block.WORKSHOP, .extended_block.WORKSHOP {background-color: #fff1e6; border-color: #fde2e4; border-left-color: #e76f51;}
        .event_block.assignment, .extended_block.assignment {background-color: #fdeaea; border-color: #f9dcdc; border-left-color: #d32f2f;}

        .extended_block {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 30vw;

            background-color: #ebcbe6;
            border-color: #fbf3f6;
            border-left-color: #edaec8;

            border: 1px solid #a9a9a9;
            border-left-width: 8px;
            border-radius: 8px;
            box-shadow: 0 8px 30px #00000080;
            padding-bottom: 10px;
            z-index: 2000;
        }

        .ext_header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #00000080;
            padding: 10px 0px 10px 0px;
            margin-bottom: 15px;
        }

        .ext_header h3 {
            font-size: 2em;
            margin: 0;
            padding-right: 15px;
        }

        .ext_buttons_container {
            display: flex;
            flex-direction: row;
            align-items: center;
            gap: 5%;
        }

        .ext_buttons {
            background: none;
            border: none;
            line-height: 0.8;
            cursor: pointer;
            font-weight: bold;
            transition: transform 0.2s;
        }

        #del_button {
            transform: translateY(5px);
            font-size: 1.3em;
        }

        #del_button:hover {
            transform: scale(1.2);  
        }

        #close_button {
            font-size: 1.8em;
            padding-right: 5px;
        }

        #close_button:hover {
            transform: scale(1.2);
        }

        .ext_body p {
            text-align: left;
            margin-bottom: 8px;
            font-size: 1.3em;
        }

        .cur_time_line {
            position: absolute;
            width: 100%;
            transform: translateY(-50%);
            border: 1px dashed #e76f51;
            z-index: 5;
        }

        .time_marker {
            position: absolute;
            width: 10px;
            height: 10px;
            transform: translate(-50%, -50%);
            background-color: #e76f51;
            border-radius: 50%;
            z-index: 5;
        }


        .top_info {
            display: flex;
            flex-direction: row;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .date_input {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            opacity: 0;
            font-size: 1.8em;
            cursor: pointer;
            pointer-events: none;
            z-index: 5;
        }

        .change_button_container {
            display: flex;
            gap: 0;
            align-items: center;
        }

        .week_change {
            background-color: #f0efeb;
            color: #999589;
            font-size: 1em;
            font-weight: bold;
            text-align: center;
            border: 2px solid #d8d2c2;
            padding: 8px 15px;
            box-shadow: 0 2px 10px #0000000d;
            transition: all 0.2s ease;
            cursor: pointer;
        }

        .week_change:hover {
            color: #f0efeb;
            background-color: #d8d2c2;
            box-shadow: 0 4px 12px #d8d2c271;
        }

        #date_container {
            position: relative;
            display: inline-block;
            cursor: pointer;
        }

        #month {
            margin: 0;
            color: #333;
            font-size: 1.8em;
            font-weight: bold;
            transition: color 0.2s;
            z-index: 10;
        }

        #month:hover {color: #e76f51}

        #today {
            color: #f0efeb;
            background-color: #d8d2c2;
        }

        #today:hover {
            color: #999589;
            background-color: #f0efeb;
            transform: scaleY(1.05);
        }

        #today:active {
            transform: scaleY(1);
        }

        #prev_week {border-radius: 8px 0px 0px 8px;}
        #prev_week:hover {transform: translateX(-3px);border-radius: 8px;}
        #prev_week:active {transform: translateX(-1px);}

        #next_week {border-radius: 0 8px 8px 0;}
        #next_week:hover {transform: translateX(3px); border-radius: 8px;}
        #next_week:active {transform: translateX(1px);}

        #invis {
            flex: 3;
            border: 1px solid #d8d2c2;
        }

    </style>

    <script>
        let week_offset = 0;
    </script>
    <script src="week_view.js" defer></script>

</head>
<body>
    <div class="header"><h1>Week View</h1></div>
    <div class="top_info">
        <div id="date_container">
            <span id="month"></span>
            <input type="date" class="date_input" id="date_selector"></input>
        </div>
        <div class="change_button_container">
            <button type="button" class="week_change" id="prev_week">&lt;</button>
            <button type="button" class="week_change" id="today">Today</button>
            <button type="button" class="week_change" id="next_week">&gt;</button>
        </div>
    </div>
    <div class='calendarContainer'>
        <div id='-1' class='day_names'>
            <div id="invis" class="day_name">Times</div>
        </div>
        <div class="week" id="WeekID">
            <div id='7' class='times'></div>
            <div id='0' class='days'></div>
            <div id='1' class='days'></div>
            <div id='2' class='days'></div>
            <div id='3' class='days'></div>
            <div id='4' class='days'></div>
            <div id='5' class='days'></div>
            <div id='6' class='days'></div>
        </div>
    </div>
    <?php include 'toolbar.php';?>
</body>
</html>