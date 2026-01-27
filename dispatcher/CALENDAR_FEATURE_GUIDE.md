# Dispatcher Calendar Feature Guide

## Overview
The Dispatcher Calendar feature provides a visual calendar interface for dispatchers to view and manage all vehicle reservations and dispatches.

## Features

### 1. Calendar Views
- **Month View**: See all dispatches for the entire month
- **Week View**: Detailed weekly view with time slots
- **Day View**: Hour-by-hour view of a single day
- **List View**: List format of all reservations

### 2. Color-Coded Status
Each dispatch is color-coded based on its status:
- **Yellow (Pending)**: Reservation is pending approval
- **Blue (Assigned)**: Reservation has been assigned to a dispatcher
- **Cyan (Approved)**: Reservation has been approved
- **Green (Completed)**: Trip has been completed
- **Red (Cancelled)**: Reservation has been cancelled

### 3. Filters
- **Status Filter**: View only specific status types (pending, approved, assigned, completed, cancelled)
- **My Assignments Only**: Toggle to show only reservations assigned to you

### 4. Event Details
Click on any event in the calendar to view:
- Reservation ID
- Requester information (name, department, contact)
- Trip details (origin, destination, purpose)
- Schedule (start and end times)
- Vehicle information (if assigned)
- Additional notes

### 5. Quick Actions
From the event details modal, you can:
- View full reservation details (redirects to My Reservations page)
- See all information at a glance

## How to Use

### Accessing the Calendar
1. Log in as a dispatcher
2. Navigate to **Dispatch Calendar** from the sidebar menu
3. The calendar will load showing all reservations

### Changing Views
Use the buttons in the toolbar:
- Click **Month**, **Week**, **Day**, or **List** to change the calendar view
- Use the navigation arrows to move between time periods
- Click **Today** to return to the current date

### Filtering Events
1. **By Status**: Select a status from the dropdown to filter events
2. **My Assignments**: Toggle the switch to show only your assigned reservations
3. The calendar will automatically refresh with the filtered results

### Viewing Event Details
1. Click on any event in the calendar
2. A modal will popup with detailed information
3. Click "View Full Details" to go to the complete reservation page

## Technical Details

### Files
- `dispatcher-calendar.php`: Main calendar page
- `get_calendar_events.php`: API endpoint that fetches calendar events from database

### Database
Reads from the `vehicle_reservations` table with the following key fields:
- `start_datetime`: Trip start date and time
- `end_datetime`: Trip end date and time
- `status`: Current status of the reservation
- `assigned_dispatcher_id`: ID of the assigned dispatcher

### Libraries Used
- **FullCalendar**: JavaScript calendar library for rendering the calendar
- **Bootstrap 5**: For responsive design and modals
- **SweetAlert2**: For beautiful alert messages
- **Font Awesome**: For icons

## Tips
- The calendar automatically refreshes when you change filters
- Events are clickable for quick details
- Use the list view for a comprehensive overview of all reservations
- The color legend at the top helps identify different status types quickly

## Support
If you encounter any issues with the calendar feature, please contact the system administrator.

