# FindIT browser checklist

Start MySQL and run start.bat. Open http://localhost:8000.

- Register an account; try invalid email, short password and duplicate email.
- Log in with correct and incorrect passwords; toggle password visibility.
- Log out and check that protected report/admin actions require login.
- Confirm a normal user cannot open admin data or use admin API operations.
- Create Lost and Found reports with an image; verify saved data after refresh.
- Try empty fields, future dates, invalid dates, negative rewards, excess decimal places and images above 1 MB.
- Double-click submit and confirm only one report appears.
- Search by title/location; filter category/status/reward; sort and paginate.
- View details as a guest and signed-in user; contact details require login.
- Use a second account to claim a report; confirm self-claims are rejected.
- As admin, reject the last pending claim and check that the original Lost/Found status returns.
- Submit competing claims; approve one and check that the item resolves and the others are rejected.
- Edit a report; verify the same record changes. Remove a report and check claims disappear.
- Check dashboard counts and reward totals against the visible records.
- Record a simulated payout only after approval. Confirm repeating the action does not add the reward twice.
- Stop the API and attempt a save: show a connection error and retain the form. Restart and retry.
- Check home, report, login, item detail and admin pages at narrow phone and desktop widths.
- Use keyboard navigation, labels, visible focus, dialog close and pagination controls.
- Check the browser console for unhandled errors and failed local asset requests.

Automated API/database coverage: php -d extension=sodium tests/integration.php (omit the extension option if sodium is already enabled).
