import icalendar
import requests
import json
url = "https://scientia-eu-v4-3-api-d3-02.azurewebsites.net//api/ical/b5098763-4476-40a6-8d60-5a08e9c52964/3d1b5c6a-384f-b3d8-69f5-10a273bae423/timetable.ics"

def parse_ical(url) -> list:
    if not url.startswith(("http://", "https://")):
        return 'Invalid URL protocol Provided'
    all_events = []


    response = requests.get(url) 
    if b'BEGIN:VCALENDAR' not in response.content:
            return 'Contents not in proper iCal format'

    cal = icalendar.Calendar.from_ical(response.content)

    for component in cal.walk():
        if component.name == "VEVENT":
            raw_start = component.get('dtstart').dt
            raw_end = component.get('dtend').dt
            
            # Calculate duration 
            duration_delta = raw_end - raw_start
            duration_mins = int(duration_delta.total_seconds() / 60)
            duration_str = f"{duration_mins}"
            start = raw_start.strftime('%Y-%m-%d %H:%M:%S')

            raw_description = str(component.get('description', ''))
        
            event_details = {}
            
            # Loop through every line in the description 
            for line in raw_description.splitlines():
                if not line.strip(): #skips empty
                    continue
                
                # Split the line at the first colon
                if ':' in line:
                    key, value = line.split(':', 1)
                    event_details[key.strip()] = value.strip()

            #using .get to add defaults
            event_data={
            'event_type' : event_details.get('Event type', 'Unknown'), #this will be title in the database 
            'description' : event_details.get('Description', 'Unknown'),
            'location' : event_details.get('Location', 'Unknown'),
            'staff' : event_details.get('Staff Member', 'Unknown'),
            'unit_code' : event_details.get('Unit Code', 'Unknown'),
            'directions' : event_details.get('Directions','Unknown'),
            'duration' : duration_str, # in minutes
            'start' : start #datetime object
             }

            
            all_events.append(event_data)
            
    return(all_events)


my_events = parse_ical(url)

with open('timetable.json', 'w') as f:
    json.dump(my_events, f, indent=4)
    
print(f"successfully saved {len(my_events)} events to timetable.json")

