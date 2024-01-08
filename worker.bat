@echo off

echo Starting 20 workers for the "voting" queue...
for /l %%i in (1, 1, 20) do (
    start "" php artisan queue:work --timeout=300 --queue=voting
)

echo Starting 10 workers for the "processing" queue...
for /l %%i in (1, 1, 10) do (
    start "" php artisan queue:work --timeout=300 --queue=processing
)

echo Workers started successfully.
