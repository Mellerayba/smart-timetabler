University timetables change constantly. Manual rescheduling is painful. So, my team and I built a system to automate it as our first year project in Computer Science at The University of Manchester. 

I’m excited to share Smart Timetabler, a distributed web app that calculates blocks of free time to resolve scheduling conflicts and automatically reschedules any missed tasks into your work/school day. 

How it works under the hood:

Our background syncing pipeline polls calendar servers every hour using JavaScript, keeping records accurate without blocking the primary user thread.
If a task becomes overdue, our system analyses your set working hours and upcoming events.
It hands this data over to a standalone Python Flask API which acts as our central processing microservice. The algorithm maps out your availability and drops the task into a non-clashing slot.
Our Python Flask API also uses mapping data to calculate the optimal time to leave for your next event based on location and mode of transport. Keeping you on track.
