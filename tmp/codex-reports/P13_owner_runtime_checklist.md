# P13 Owner runtime checklist

Status before owner check: `P13_CODE_READY_RUNTIME_OWNER_CHECK_REQUIRED`

1. Open `/erpv2/company/trips/linear`.
2. Open an existing editable trip.
3. Select a small allowed file (for example PDF under 20 MB).
4. Confirm the selected filename is visible and the clear button removes it before submit.
5. Select the file again and click **Сохранить** once.
6. Confirm the button becomes disabled and shows `Сохранение…`.
7. Confirm a success message appears and no generic error is shown.
8. Reopen the trip and verify the document is listed.
9. Open/view and download the document.
10. Save the trip again without selecting a new file; verify no duplicate document appears.
11. Replace a predefined document; verify the new one is visible and the old one is not removed before successful replacement.
12. Try a forbidden extension and verify a specific safe error is shown while the modal and entered values remain.
13. Try an empty/oversized file where practical and verify a specific message.
14. Check Network: no 404/403/419/500 on the valid save.
15. Check Console: no JavaScript errors.
16. Confirm all requests remain under `/erpv2`, not `/erp`.

Only after all checks pass may the status be promoted to `P13_LINEAR_TRIP_DOCUMENT_SAVE_ACCEPTED`.
