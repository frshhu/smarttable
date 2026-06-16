<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartTable - Interaktywny Plan Sali</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .floor-plan {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 35px;
            background: #f8f9fa;
            padding: 40px;
            border-radius: 15px;
            border: 2px solid #dee2e6;
        }
        .table-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 10px;
            cursor: pointer;
            transition: all 0.25s ease;
            user-select: none;
        }
        .seats-row {
            display: flex;
            gap: 8px;
            justify-content: center;
            min-height: 14px;
        }
        .seat-chair {
            width: 14px;
            height: 14px;
            background-color: #28a745;
            border: 1px solid #1e7e34;
            border-radius: 4px;
            transition: all 0.2s ease;
        }
        .table-surface {
            width: 85px;
            height: 50px;
            background-color: #28a745;
            border: 2px solid #1e7e34;
            border-radius: 10px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 13px;
            margin: 8px 0;
            box-shadow: 0 4px 8px rgba(0,0,0,0.11);
            transition: all 0.2s ease;
        }

        /* --- BLOKADY I STATUSY STOLIKÓW --- */
        .table-container.disabled-status {
            cursor: not-allowed;
        }
        /* Stolik zajęty (Szary) */
        .table-container.taken .seat-chair { background-color: #6c757d; border-color: #545b62; }
        .table-container.taken .table-surface { background-color: #6c757d; border-color: #545b62; box-shadow: none; }
        
        /* Stolik niedopasowany rozmiarem (Pomarańczowy / Wyblakły) */
        .table-container.bad-size { opacity: 0.35; }
        .table-container.bad-size .seat-chair { background-color: #e67e22; border-color: #d35400; }
        .table-container.bad-size .table-surface { background-color: #e67e22; border-color: #d35400; box-shadow: none; }

        /* --- STYL WYBRANEGO STOLIKA (Niebieski) --- */
        .table-container.selected-active .seat-chair { background-color: #0056b3; border-color: #004085; transform: scale(1.1); }
        .table-container.selected-active .table-surface { background-color: #0056b3; border-color: #004085; transform: scale(1.05); box-shadow: 0 6px 12px rgba(0,86,179,0.3); }

        .table-container:not(.disabled-status):hover .table-surface { background-color: #218838; transform: scale(1.03); }
        .table-container:not(.disabled-status):hover .seat-chair { background-color: #218838; }
    </style>
</head>
<body class="bg-light">
    <div class="container my-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card shadow border-0">
                    <div class="card-header bg-dark text-white text-center py-3">
                        <h3 class="mb-0">SmartTable - Rezerwacja z Planem Sali</h3>
                    </div>
                    <div class="card-body p-4">
                        <form action="index.php?action=submit" method="POST" id="bookingForm">
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="date" class="form-label fw-bold">1. Wybierz datę</label>
                                    <input type="date" class="form-control" id="date" name="date" required min="<?= date('Y-m-d'); ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="time" class="form-label fw-bold">2. Wybierz godzinę</label>
                                    <select class="form-select" id="time" name="time" required>
                                        <option value="">-- Wybierz godzinę --</option>
                                        <option value="12:00">12:00</option>
                                        <option value="13:00">13:00</option>
                                        <option value="14:00">14:00</option>
                                        <option value="15:00">15:00</option>
                                        <option value="16:00">16:00</option>
                                        <option value="17:00">17:00</option>
                                        <option value="18:00">18:00</option>
                                        <option value="19:00">19:00</option>
                                        <option value="20:00">20:00</option>
                                        <option value="21:00">21:00</option>
                                    </select>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label for="party_size" class="form-label fw-bold">3. Liczba gości (Maksymalnie 6 osób)</label>
                                <input type="number" class="form-control" id="party_size" name="party_size" min="1" max="6" required value="2">
                            </div>

                            <div class="mb-4">
                                <label class="form-label text-dark d-block h5 fw-bold">4. Wybierz wolny stolik:</label>
                                <small class="text-muted d-block mb-3">System automatycznie blokuje zbyt duże lub za małe stoliki dla wybranej liczby gości.</small>
                                
                                <div id="floorPlanContainer" class="floor-plan">
                                    <p class="text-center text-muted col-span-3 w-100 my-3">Wprowadź datę i godzinę, aby załadować mapę sali...</p>
                                </div>
                            </div>

                            <input type="hidden" id="selected_table_id" name="selected_table_id" required>

                            <div id="contactSection" style="display: none;" class="border-top pt-4">
                                <h5 class="text-secondary mb-3 fw-bold">5. Dane kontaktowe</h5>
                                <div class="mb-3">
                                    <label for="name" class="form-label">Imię i Nazwisko</label>
                                    <input type="text" class="form-control" id="name" name="name" required>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="phone" class="form-label">Numer telefonu</label>
                                        <input type="tel" class="form-control" id="phone" name="phone" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="email" class="form-label">Adres E-mail</label>
                                        <input type="email" class="form-control" id="email" name="email" required>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-success w-100 btn-lg mt-3">Potwierdź rezerwację wybranego miejsca</button>
                            </div>

                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const dateInput = document.getElementById('date');
        const timeInput = document.getElementById('time');
        const partySizeInput = document.getElementById('party_size');
        const floorPlanContainer = document.getElementById('floorPlanContainer');
        const selectedTableInput = document.getElementById('selected_table_id');
        const contactSection = document.getElementById('contactSection');

        // Automatyczne przeładowanie planu sali, jeśli zmieni się liczba gości!
        [dateInput, timeInput, partySizeInput].forEach(input => {
            input.addEventListener('change', fetchTableStatus);
        });

        function fetchTableStatus() {
            const date = dateInput.value;
            const time = timeInput.value;
            const partySize = parseInt(partySizeInput.value) || 1;

            // Zabezpieczenie przed wpisaniem z klawiatury wartości powyżej 6
            if (partySize > 6) {
                alert("Maksymalna liczba gości to 6 osób.");
                partySizeInput.value = 6;
                return;
            }

            if (!date || !time) return;

            floorPlanContainer.innerHTML = '<div class="text-center col-span-3 w-100 p-3">🔄 Budowanie widoku graficznego sali...</div>';
            
            fetch(`index.php?action=check-availability&date=${date}&time=${time}`)
                .then(response => response.json())
                .then(tables => {
                    floorPlanContainer.innerHTML = '';
                    
                    tables.forEach(table => {
                        const tableBox = document.createElement('div');
                        tableBox.className = 'table-container';
                        
                        let isSelectable = true;
                        let statusText = 'Wolny';

                        // --- REGUŁA BIZNESOWA: DOPASOWANIE ROZMIARU GRUPY ---
                        if (partySize > table.capacity) {
                            isSelectable = false;
                            statusText = 'Za mały';
                            tableBox.classList.add('disabled-status', 'bad-size');
                        } 
                        // Blokada marnowania miejsc (np. 1 osoba na stolik 4/6 osób)
                        else if (partySize === 1 && table.capacity > 2) {
                            isSelectable = false;
                            statusText = 'Tylko dla 2+ os.';
                            tableBox.classList.add('disabled-status', 'bad-size');
                        } 
                        // Blokada 2 osób na stolikach 6-osobowych
                        else if (partySize === 2 && table.capacity > 4) {
                            isSelectable = false;
                            statusText = 'Tylko dla 3+ os.';
                            tableBox.classList.add('disabled-status', 'bad-size');
                        }

                        // Jeśli stolik jest po prostu fizycznie zajęty w bazie danych [cite: 90]
                        if (table.is_taken) {
                            isSelectable = false;
                            statusText = 'Zajęty';
                            tableBox.className = 'table-container disabled-status taken';
                        }

                        // Generowanie krzesełek wokół stołu
                        const topSeatsCount = Math.ceil(table.capacity / 2);
                        const bottomSeatsCount = Math.floor(table.capacity / 2);
                        
                        let topSeatsHTML = ''; for(let i=0; i<topSeatsCount; i++) topSeatsHTML += '<div class="seat-chair"></div>';
                        let bottomSeatsHTML = ''; for(let i=0; i<bottomSeatsCount; i++) bottomSeatsHTML += '<div class="seat-chair"></div>';
                        
                        tableBox.innerHTML = `
                            <div class="seats-row">${topSeatsHTML}</div>
                            <div class="table-surface">
                                <div>Stolik ${table.table_number}</div>
                                <div style="font-size: 9px; font-weight: normal; opacity: 0.9;">${statusText}</div>
                            </div>
                            <div class="seats-row">${bottomSeatsHTML}</div>
                        `;
                        
                        // Podłączenie kliknięcia tylko do w pełni dostępnych stolików
                        if (isSelectable) {
                            tableBox.addEventListener('click', function() {
                                document.querySelectorAll('.table-container').forEach(b => b.classList.remove('selected-active'));
                                tableBox.classList.add('selected-active');
                                selectedTableInput.value = table.table_id;
                                contactSection.style.display = 'block';
                                contactSection.scrollIntoView({ behavior: 'smooth' });
                            });
                        }

                        floorPlanContainer.appendChild(tableBox);
                    });
                });
        }
    </script>
</body>
</html>