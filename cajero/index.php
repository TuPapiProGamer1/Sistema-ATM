<?php
session_start();

// Database handling functions
function readDatabase()
{
    if (!file_exists('db.json')) {
        // Create sample database if it doesn't exist
        $sampleData = [
            'users' => [
                [
                    'username' => 'user1',
                    'pin' => '1234',
                    'balance' => 2000
                ],
                [
                    'username' => 'user2',
                    'pin' => '5678',
                    'balance' => 1500
                ]
            ]
        ];
        file_put_contents('db.json', json_encode($sampleData, JSON_PRETTY_PRINT));
    }

    return json_decode(file_get_contents('db.json'), true);
}

function saveDatabase($data)
{
    file_put_contents('db.json', json_encode($data, JSON_PRETTY_PRINT));
}

function findUser($username, $pin = null)
{
    $data = readDatabase();
    foreach ($data['users'] as $index => $user) {
        if ($user['username'] === $username) {
            if ($pin === null || $user['pin'] === $pin) {
                return ['user' => $user, 'index' => $index];
            }
        }
    }
    return null;
}

function updateUserBalance($username, $newBalance)
{
    $data = readDatabase();
    $userInfo = findUser($username);

    if ($userInfo) {
        $data['users'][$userInfo['index']]['balance'] = $newBalance;
        saveDatabase($data);
        return true;
    }

    return false;
}

function registerNewUser($username, $pin, $initialBalance = 500)
{
    $data = readDatabase();

    // Check if username already exists
    if (findUser($username)) {
        return false;
    }

    // Add new user
    $data['users'][] = [
        'username' => $username,
        'pin' => $pin,
        'balance' => $initialBalance
    ];

    saveDatabase($data);
    return true;
}

function generateReceiptCode()
{
    // Format: YYYYMMDD-HHMM-XXXX (X = random digits)
    $datePrefix = date('Ymd-Hi');
    $randomSuffix = sprintf("%04d", mt_rand(1, 9999));
    return $datePrefix . '-' . $randomSuffix;
}

// Process form submissions
$message = '';
$screen = isset($_SESSION['screen']) ? $_SESSION['screen'] : 'welcome';

if (isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'login':
            if (isset($_POST['username']) && isset($_POST['pin'])) {
                $userInfo = findUser($_POST['username'], $_POST['pin']);
                if ($userInfo) {
                    $_SESSION['user'] = $userInfo['user'];
                    $screen = 'main_menu';
                } else {
                    $message = 'Nombre de usuario o PIN Incorrecto';
                    $screen = 'welcome';
                }
            }
            break;

        case 'show_register':
            $screen = 'register';
            break;

        case 'register':
            if (isset($_POST['username']) && isset($_POST['pin']) && strlen($_POST['pin']) === 4) {
                if (registerNewUser($_POST['username'], $_POST['pin'])) {
                    $message = '¡Registro existoso! Ahora puede iniciar sesión.';
                    $screen = 'welcome';
                } else {
                    $message = 'Usuario ya existente. Por favor escoga otro o inice sesión.';
                    $screen = 'register';
                }
            } else {
                $message = 'Por favor ingrese un nombre de usuario válido y un PIN de 4 dígitos.';
                $screen = 'register';
            }
            break;

        case 'withdraw_menu':
            $screen = 'withdraw_menu';
            break;

        case 'deposit_menu':
            $screen = 'deposit_menu';
            break;

        case 'balance':
            $screen = 'balance';
            break;

        case 'withdraw':
            if (isset($_SESSION['user']) && isset($_POST['amount'])) {
                $amount = (int) $_POST['amount'];
                $currentBalance = $_SESSION['user']['balance'];

                // Validate amount
                $isValid = true;

                // Check minimum and maximum
                if ($amount < 10 || $amount > 1000) {
                    $isValid = false;
                }

                // Check if multiple of 10
                if ($amount % 10 != 0) {
                    $isValid = false;
                }

                if (!$isValid) {
                    $message = 'El monto debe ser entre Bs. 10 y Bs. 1000, y múltiplo de 10';
                    $screen = 'withdraw_menu';
                    break;
                }

                if ($amount <= $currentBalance) {
                    // Calculate bills
                    $bills = calculateBills($amount);
                    if ($bills) {
                        $newBalance = $currentBalance - $amount;
                        updateUserBalance($_SESSION['user']['username'], $newBalance);
                        $_SESSION['user']['balance'] = $newBalance;
                        $_SESSION['bills'] = $bills;
                        $screen = 'dispense_cash';
                    } else {
                        $message = 'No se puede dispensar este monto con los billetes disponibles';
                        $screen = 'withdraw_menu';
                    }
                } else {
                    $message = 'Fondos insuficientes';
                    $screen = 'withdraw_menu';
                }
            }
            break;

        case 'deposit':
            if (isset($_SESSION['user']) && isset($_POST['amount'])) {
                $amount = (int) $_POST['amount'];
                $newBalance = $_SESSION['user']['balance'] + $amount;
                updateUserBalance($_SESSION['user']['username'], $newBalance);
                $_SESSION['user']['balance'] = $newBalance;
                // Store the amount in session
                $_SESSION['deposit_amount'] = $amount;
                $message = 'Se depositó existosamente Bs. ' . $amount;
                $screen = 'deposit_success';
            }
            break;

        case 'collect_cash':
            $screen = 'receipt';
            break;

        case 'back_to_menu':
            $screen = 'main_menu';
            break;

        case 'back_to_welcome':
            $screen = 'welcome';
            break;

        case 'logout':
            session_destroy();
            $screen = 'welcome';
            break;
    }
}

$_SESSION['screen'] = $screen;

// Calculate the optimal combination of bills
function calculateBills($amount)
{
    $availableBills = [200, 100, 50, 20, 10 , 5];
    $result = [];
    // Check if amount is one of the preset values or a valid custom amount
    $presetValues = [100, 200, 500, 800, 1000];
    // Always proceed for valid amounts (we already validated it's ≥10, ≤1000, and multiple of 10)
    if (!in_array($amount, $presetValues) && ($amount < 5 || $amount > 1000 || $amount % 5 != 0)) {
        return false;
    }

    // Distribute bills in descending order
    foreach ($availableBills as $bill) {
        $count = intdiv($amount, $bill);
        if ($count > 0) {
            $result[$bill] = $count;
            $amount -= $count * $bill;
        }
    }

    // If we couldn't make up the exact amount, return false
    if ($amount > 0) {
        return false;
    }

    return $result;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema ATM</title>
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <div class="atm-container">
        <div class="atm-frame">
            <div class="atm-header">ATM</div>

            <div class="atm-screen">
                <?php if ($message): ?>
                    <?php
                    // Check if this is a success message
                    $messageClass = 'message'; // Default error style
                    if (
                        strpos($message, 'existoso') !== false ||
                        strpos($message, 'depositó') !== false ||
                        strpos($message, 'Registro') !== false
                    ) {
                        $messageClass = 'good-message'; // Success style
                    }
                    ?>
                    <div class="<?php echo $messageClass; ?>"><?php echo $message; ?></div>
                <?php endif; ?>

                <?php if ($screen === 'welcome'): ?>
                    <div class="screen-content welcome-screen">
                        <h2>Bienvenido al Banco Cruceño</h2>
                        <p>Por favor ingrese sus credenciales</p>
                        <form method="post" class="login-form" id="login-form">
                            <input type="hidden" name="action" value="login">
                            <div class="form-group">
                                <label for="username">Usuario:</label>
                                <input type="text" id="username" name="username" required>
                            </div>
                            <div class="form-group">
                                <label for="pin">PIN:</label>
                                <input type="password" id="pin" name="pin" maxlength="4" required>
                            </div>
                            <button type="submit" class="screen-button">Login</button>
                        </form>
                        <div class="register-option">
                            <form method="post">
                                <input type="hidden" name="action" value="show_register">
                                <button type="submit" class="register-button">Registrar Usuario</button>
                            </form>
                        </div>
                    </div>

                <?php elseif ($screen === 'register'): ?>
                    <div class="screen-content register-screen">
                        <h2>Registrar Usuario</h2>
                        <form method="post" class="register-form" id="register-form">
                            <input type="hidden" name="action" value="register">
                            <div class="form-group">
                                <label for="new-username">Usuario:</label>
                                <input type="text" id="new-username" name="username" required>
                            </div>
                            <div class="form-group">
                                <label for="new-pin">PIN (4 dígitos):</label>
                                <input type="password" id="new-pin" name="pin" maxlength="4" pattern="[0-9]{4}" required>
                            </div>
                            <div class="form-group">
                                <label>Saldo Inicial:</label>
                                <p>Bs. 500</p>
                            </div>
                            <button type="submit" class="screen-button">Registrar</button>
                        </form>
                    </div>

                <?php elseif ($screen === 'main_menu'): ?>
                    <div class="screen-content main-menu">
                        <h2>Bienvenido, <?php echo $_SESSION['user']['username']; ?></h2>
                        <p>Por favor seleccione una operación:</p>
                        <div class="menu-options">
                            <form method="post" id="withdraw-form">
                                <input type="hidden" name="action" value="withdraw_menu">
                                <button type="submit" class="menu-button">Retirar</button>
                            </form>
                            <form method="post" id="deposit-form">
                                <input type="hidden" name="action" value="deposit_menu">
                                <button type="submit" class="menu-button">Depositar</button>
                            </form>
                            <form method="post" id="balance-form">
                                <input type="hidden" name="action" value="balance">
                                <button type="submit" class="menu-button">Revisar Saldo</button>
                            </form>
                            <form method="post" id="logout-form">
                                <input type="hidden" name="action" value="logout">
                                <button type="submit" class="menu-button">Logout</button>
                            </form>
                        </div>
                    </div>

                <?php elseif ($screen === 'withdraw_menu'): ?>
                    <div class="screen-content withdraw-menu">
                        <h2>Retirar Dinero</h2>
                        <p>Selecciona la cantidad para retirar:</p>
                        <div class="amount-options">
                            <form method="post" class="withdraw-form" data-amount="100">
                                <input type="hidden" name="action" value="withdraw">
                                <input type="hidden" name="amount" value="100">
                                <button type="submit" class="amount-button">Bs. 100</button>
                            </form>
                            <form method="post" class="withdraw-form" data-amount="200">
                                <input type="hidden" name="action" value="withdraw">
                                <input type="hidden" name="amount" value="200">
                                <button type="submit" class="amount-button">Bs. 200</button>
                            </form>
                            <form method="post" class="withdraw-form" data-amount="500">
                                <input type="hidden" name="action" value="withdraw">
                                <input type="hidden" name="amount" value="500">
                                <button type="submit" class="amount-button">Bs. 500</button>
                            </form>
                            <form method="post" class="withdraw-form" data-amount="800">
                                <input type="hidden" name="action" value="withdraw">
                                <input type="hidden" name="amount" value="800">
                                <button type="submit" class="amount-button">Bs. 800</button>
                            </form>
                            <form method="post" class="withdraw-form" data-amount="1000">
                                <input type="hidden" name="action" value="withdraw">
                                <input type="hidden" name="amount" value="1000">
                                <button type="submit" class="amount-button">Bs. 1000</button>
                            </form>
                            <form method="post" class="custom-amount-form">
                                <input type="hidden" name="action" value="withdraw">
                                <div class="form-group">
                                    <label for="custom-amount">Monto Personalizado:</label>
                                    <input type="number" id="custom-amount" name="amount" min="10" max="1000" step="10"
                                        placeholder="Bs.">
                                </div>
                                <button type="submit" class="screen-button custom-amount-button">Retirar</button>
                            </form>
                        </div>
                    </div>

                <?php elseif ($screen === 'deposit_menu'): ?>
                    <div class="screen-content deposit-menu">
                        <h2>Depositar Dinero</h2>
                        <p>Selecciona la cantidad para depositar:</p>
                        <div class="amount-options">
                            <form method="post" class="deposit-form" data-amount="100">
                                <input type="hidden" name="action" value="deposit">
                                <input type="hidden" name="amount" value="100">
                                <button type="submit" class="amount-button">Bs. 100</button>
                            </form>
                            <form method="post" class="deposit-form" data-amount="200">
                                <input type="hidden" name="action" value="deposit">
                                <input type="hidden" name="amount" value="200">
                                <button type="submit" class="amount-button">Bs. 200</button>
                            </form>
                            <form method="post" class="deposit-form" data-amount="500">
                                <input type="hidden" name="action" value="deposit">
                                <input type="hidden" name="amount" value="500">
                                <button type="submit" class="amount-button">Bs. 500</button>
                            </form>
                            <form method="post" class="deposit-form" data-amount="1000">
                                <input type="hidden" name="action" value="deposit">
                                <input type="hidden" name="amount" value="1000">
                                <button type="submit" class="amount-button">Bs. 1000</button>
                            </form>
                        </div>
                    </div>

                <?php elseif ($screen === 'balance'): ?>
                    <div class="screen-content balance-screen">
                        <h2>Tu Saldo</h2>
                        <div class="balance-display">
                            <p>Saldo actual: <span class="balance-amount">Bs.
                                    <?php echo $_SESSION['user']['balance']; ?></span></p>
                        </div>
                    </div>

                <?php elseif ($screen === 'dispense_cash'): ?>
                    <div class="screen-content dispense-screen">
                        <h2>Recoge tu Dinero</h2>
                        <p>Por favor toma tu dinero de la ranura</p>
                        <div class="bills-container">
                            <?php foreach ($_SESSION['bills'] as $bill => $count): ?>
                                <?php for ($i = 0; $i < $count; $i++): ?>
                                    <img src="bills/<?php echo $bill; ?>bs.png" class="bill-image"
                                        alt="<?php echo $bill; ?> Bolivianos" data-bill="<?php echo $bill; ?>">
                                <?php endfor; ?>
                            <?php endforeach; ?>
                        </div>
                        <p>Toca cada billete para tomarlo</p>
                        <form method="post" id="collect-form" style="display: none;">
                            <input type="hidden" name="action" value="collect_cash">
                            <button type="submit" id="continue-button">Continuar</button>
                        </form>
                    </div>

                <?php elseif ($screen === 'receipt'): ?>
                    <div class="screen-content receipt-screen">
                        <h2>Recibo de Transacción</h2>
                        <div class="receipt-details">
                            <p>Tipo de Transacción: Retiro</p>
                            <p>Cantidad: Bs. <?php
                                                $totalAmount = 0;
                                                foreach ($_SESSION['bills'] as $bill => $count) {
                                                    $totalAmount += $bill * $count;
                                                }
                                                echo $totalAmount;
                                                ?></p>
                            <p>Nuevo Saldo: Bs. <?php echo $_SESSION['user']['balance']; ?></p>
                            <p>Fecha: <?php echo date('Y-m-d H:i:s'); ?></p>
                        </div>
                        <div class="receipt-actions">
                            <p>Código de Recibo: <span id="receipt-code"><?php echo generateReceiptCode(); ?></span></p>
                            <button type="button" class="print-button" onclick="printReceiptSafely()">Imprimir
                                Recibo</button>
                        </div>
                        <!-- Add a form for back to menu button -->
                        <form method="post" id="back-to-menu-form" style="display: none;">
                            <input type="hidden" name="action" value="back_to_menu">
                            <button type="submit" id="back-to-menu-button">Volver al Menú</button>
                        </form>
                    </div>

                <?php elseif ($screen === 'deposit_success'): ?>
                    <div class="screen-content deposit-success">
                        <h2>Depósito Existoso</h2>
                        <div class="receipt-details">
                            <p>Tipo de Transacción: Depósito</p>
                            <p>Cantidad: Bs.
                                <?php echo isset($_SESSION['deposit_amount']) ? $_SESSION['deposit_amount'] : '0'; ?>
                            </p>
                            <p>Nuevo Saldo: Bs. <?php echo $_SESSION['user']['balance']; ?></p>
                            <p>Fecha: <?php echo date('Y-m-d H:i:s'); ?></p>
                        </div>
                        <div class="receipt-actions">
                            <p>Código de Recibo: <span id="receipt-code"><?php echo generateReceiptCode(); ?></span></p>
                            <button type="button" class="print-button" onclick="printReceiptSafely()">Imprimir
                                Recibo</button>
                        </div>
                        <!-- Add a form for back to menu button -->
                        <form method="post" id="back-to-menu-form" style="display: none;">
                            <input type="hidden" name="action" value="back_to_menu">
                            <button type="submit" id="back-to-menu-button">Volver al Menú</button>
                        </form>
                    </div>
                    <?php
                    // Clear the deposit amount from session after displaying
                    if (isset($_SESSION['deposit_amount'])) {
                        unset($_SESSION['deposit_amount']);
                    }
                    ?>
                <?php endif; ?>
            </div>

            <div class="atm-keypad">
                <div class="keypad-row">
                    <button type="button" class="keypad-button number-key" data-key="1">1</button>
                    <button type="button" class="keypad-button number-key" data-key="2">2</button>
                    <button type="button" class="keypad-button number-key" data-key="3">3</button>
                    <button type="button" class="keypad-button red" id="cancel-button">Back</button>
                </div>
                <div class="keypad-row">
                    <button type="button" class="keypad-button number-key" data-key="4">4</button>
                    <button type="button" class="keypad-button number-key" data-key="5">5</button>
                    <button type="button" class="keypad-button number-key" data-key="6">6</button>
                    <button type="button" class="keypad-button yellow" id="clear-button">Clear</button>
                </div>
                <div class="keypad-row">
                    <button type="button" class="keypad-button number-key" data-key="7">7</button>
                    <button type="button" class="keypad-button number-key" data-key="8">8</button>
                    <button type="button" class="keypad-button number-key" data-key="9">9</button>
                    <button type="button" class="keypad-button green" id="enter-button">Enter</button>
                </div>
                <div class="keypad-row">
                    <button type="button" class="keypad-button empty"></button>
                    <button type="button" class="keypad-button number-key" data-key="0">0</button>
                    <button type="button" class="keypad-button empty"></button>
                    <button type="button" class="keypad-button empty"></button>
                </div>
            </div>

            <div class="atm-footer">
                <div class="footer-message">Banco Cruceño ATM by Diego Fernández & Fabricio Zeballos ©</div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Current active input element
            let activeInput = null;

            // Set up active input for input fields
            function setActiveInput() {
                const screen = '<?php echo $screen; ?>';

                if (screen === 'welcome') {
                    // Focus on username first, if empty
                    const usernameInput = document.getElementById('username');
                    const pinInput = document.getElementById('pin');

                    if (!usernameInput.value) {
                        activeInput = usernameInput;
                    } else {
                        activeInput = pinInput;
                    }

                    if (activeInput) activeInput.focus();
                } else if (screen === 'register') {
                    // Focus on new username first, if empty
                    const newUsernameInput = document.getElementById('new-username');
                    const newPinInput = document.getElementById('new-pin');

                    if (!newUsernameInput.value) {
                        activeInput = newUsernameInput;
                    } else {
                        activeInput = newPinInput;
                    }

                    if (activeInput) activeInput.focus();
                } else if (screen === 'withdraw_menu') {
                    // Set custom amount field as active
                    const customAmount = document.getElementById('custom-amount');
                    if (customAmount) {
                        activeInput = customAmount;
                        activeInput.focus();
                    }
                }
            }

            // Set initial active input
            setActiveInput();

            // Switch active input when clicking on input fields
            const inputFields = document.querySelectorAll('input[type="text"], input[type="password"], input[type="number"]');
            inputFields.forEach(input => {
                input.addEventListener('focus', function() {
                    activeInput = this;
                });
            });

            // Handle keypad number buttons
            function setupNumberKeys() {
                const numberKeys = document.querySelectorAll('.number-key');
                numberKeys.forEach(button => {
                    button.addEventListener('click', function() {
                        if (activeInput) {
                            const key = this.getAttribute('data-key');

                            // For PIN fields, limit to 4 digits
                            if (activeInput.type === 'password' && activeInput.value.length < 4) {
                                activeInput.value += key;
                            }
                            // For username or number fields
                            else if (activeInput.type === 'text' || activeInput.type === 'number') {
                                activeInput.value += key;
                            }
                        } else {
                            // If no active input, check for quick access buttons in certain screens
                            const screen = '<?php echo $screen; ?>';

                            // Quick access for withdraw menu
                            if (screen === 'withdraw_menu') {
                                const keyToAmount = {
                                    '1': 100,
                                    '2': 200,
                                    '5': 500,
                                    '8': 800,
                                    '0': 1000
                                };

                                const key = this.getAttribute('data-key');
                                const amount = keyToAmount[key];

                                if (amount) {
                                    const form = document.querySelector(`.withdraw-form[data-amount="${amount}"]`);
                                    if (form) form.submit();
                                }
                            }

                            // Quick access for deposit menu
                            else if (screen === 'deposit_menu') {
                                const keyToAmount = {
                                    '1': 100,
                                    '2': 200,
                                    '5': 500,
                                    '0': 1000
                                };

                                const key = this.getAttribute('data-key');
                                const amount = keyToAmount[key];

                                if (amount) {
                                    const form = document.querySelector(`.deposit-form[data-amount="${amount}"]`);
                                    if (form) form.submit();
                                }
                            }

                            // Quick access for main menu
                            else if (screen === 'main_menu') {
                                const keyToForm = {
                                    '1': 'withdraw-form',
                                    '2': 'deposit-form',
                                    '3': 'balance-form',
                                    '4': 'logout-form'
                                };

                                const key = this.getAttribute('data-key');
                                const formId = keyToForm[key];

                                if (formId) {
                                    const form = document.getElementById(formId);
                                    if (form) form.submit();
                                }
                            }
                        }
                    });
                });
            }

            // Set up number keys
            setupNumberKeys();

            // Handle clear button
            function setupClearButton() {
                const clearButton = document.getElementById('clear-button');
                if (clearButton) {
                    clearButton.addEventListener('click', function() {
                        if (activeInput) {
                            activeInput.value = '';
                        }
                    });
                }
            }

            // Set up clear button
            setupClearButton();

            // Handle enter button
            function setupEnterButton() {
                const enterButton = document.getElementById('enter-button');
                if (enterButton) {
                    enterButton.addEventListener('click', function() {
                        const screen = '<?php echo $screen; ?>';

                        if (screen === 'welcome') {
                            const loginForm = document.getElementById('login-form');
                            if (loginForm) loginForm.submit();
                        } else if (screen === 'register') {
                            const registerForm = document.getElementById('register-form');
                            if (registerForm) registerForm.submit();
                        } else if (screen === 'main_menu') {
                            // Default to balance check when pressing enter from main menu
                            const balanceForm = document.getElementById('balance-form');
                            if (balanceForm) balanceForm.submit();
                        } else if (screen === 'withdraw_menu') {
                            // Submit custom amount form if there's a value
                            const customAmountInput = document.getElementById('custom-amount');
                            if (customAmountInput && customAmountInput.value) {
                                const customAmountForm = document.querySelector('.custom-amount-form');
                                if (customAmountForm) customAmountForm.submit();
                            }
                        } else if (screen === 'dispense_cash') {
                            // Collect all bills automatically
                            const bills = document.querySelectorAll('.bill-image');
                            bills.forEach(bill => bill.style.display = 'none');

                            const collectForm = document.getElementById('collect-form');
                            if (collectForm) {
                                collectForm.style.display = 'block';
                                const continueButton = document.getElementById('continue-button');
                                if (continueButton) continueButton.click();
                            }
                        } else if (screen === 'receipt' || screen === 'deposit_success') {
                            // Return to main menu
                            const backToMenuForm = document.getElementById('back-to-menu-form');
                            if (backToMenuForm) backToMenuForm.submit();
                        }
                    });
                }
            }

            // Set up enter button
            setupEnterButton();

            // Handle cancel/back button
            function setupCancelButton() {
                const cancelButton = document.getElementById('cancel-button');
                if (cancelButton) {
                    cancelButton.addEventListener('click', function() {
                        const screen = '<?php echo $screen; ?>';

                        if (screen === 'welcome') {
                            // Clear fields for welcome screen
                            const inputs = document.querySelectorAll('input[type="text"], input[type="password"]');
                            inputs.forEach(input => {
                                input.value = '';
                            });
                        } else if (screen === 'register') {
                            // Go back to welcome from register
                            const form = document.createElement('form');
                            form.method = 'POST';
                            const input = document.createElement('input');
                            input.type = 'hidden';
                            input.name = 'action';
                            input.value = 'back_to_welcome';
                            form.appendChild(input);
                            document.body.appendChild(form);
                            form.submit();
                        } else if (screen === 'main_menu') {
                            // Logout from main menu
                            const form = document.createElement('form');
                            form.method = 'POST';
                            const input = document.createElement('input');
                            input.type = 'hidden';
                            input.name = 'action';
                            input.value = 'logout';
                            form.appendChild(input);
                            document.body.appendChild(form);
                            form.submit();
                        } else if (screen === 'withdraw_menu' || screen === 'deposit_menu' ||
                            screen === 'balance' || screen === 'receipt' ||
                            screen === 'deposit_success') {
                            // Go back to main menu from these screens
                            const backForm = document.getElementById('back-to-menu-form');
                            if (backForm) {
                                backForm.submit();
                            } else {
                                // Fallback method if the form doesn't exist
                                const form = document.createElement('form');
                                form.method = 'POST';
                                const input = document.createElement('input');
                                input.type = 'hidden';
                                input.name = 'action';
                                input.value = 'back_to_menu';
                                form.appendChild(input);
                                document.body.appendChild(form);
                                form.submit();
                            }
                        } else if (screen === 'dispense_cash') {
                            // For dispense screen, continue to receipt
                            const bills = document.querySelectorAll('.bill-image');
                            bills.forEach(bill => bill.style.display = 'none');

                            const collectForm = document.getElementById('collect-form');
                            if (collectForm) {
                                collectForm.style.display = 'block';
                                const continueButton = document.getElementById('continue-button');
                                if (continueButton) continueButton.click();
                            }
                        }
                    });
                }
            }

            // Set up cancel button
            setupCancelButton();

            // Handle bill collection
            function setupBillCollection() {
                const bills = document.querySelectorAll('.bill-image');
                const collectForm = document.getElementById('collect-form');

                if (bills.length > 0 && collectForm) {
                    let collectedBills = 0;
                    const totalBills = bills.length;

                    bills.forEach(bill => {
                        bill.addEventListener('click', function() {
                            this.style.display = 'none';
                            collectedBills++;

                            if (collectedBills === totalBills) {
                                collectForm.style.display = 'block';
                                const continueButton = document.getElementById('continue-button');
                                if (continueButton) continueButton.click();
                            }
                        });
                    });
                }
            }

            // Set up bill collection
            setupBillCollection();

            // Handle receipt printing
            function setupReceiptPrinting() {
                const printButton = document.querySelector('.print-button');
                if (printButton) {
                    printButton.addEventListener('click', function() {
                        printReceipt();
                    });
                }
            }

            // Set up receipt printing
            setupReceiptPrinting();

            // Print receipt function
            function printReceipt() {
                // Store the current page content
                const originalContent = document.body.innerHTML;

                // Create print-only content
                const printContent = document.createElement('div');
                printContent.className = 'print-receipt';

                // Get transaction details
                const receiptDetails = document.querySelector('.receipt-details');
                const receiptCode = document.getElementById('receipt-code').textContent;

                // Create header with bank name and logo
                const header = document.createElement('div');
                header.innerHTML = `
            <h1>Banco Cruceño</h1>
            <h2>Comprobante de Transacción</h2>
            <p><strong>Código de Recibo:</strong> ${receiptCode}</p>
            <hr>
        `;

                // Create footer with disclaimer
                const footer = document.createElement('div');
                footer.innerHTML = `
            <hr>
            <p>Gracias por utilizar nuestros servicios.</p>
            <p>Por favor, conserve este recibo para futuras referencias.</p>
            <p>Servicio de Atención al Cliente: 800-123-4567</p>
        `;

                // Combine all parts
                printContent.appendChild(header);
                printContent.appendChild(receiptDetails.cloneNode(true));
                printContent.appendChild(footer);

                // Replace page content with print-friendly version
                document.body.innerHTML = printContent.outerHTML;

                // Print the document
                window.print();

                // Restore original content
                document.body.innerHTML = originalContent;

                // Reattach event listeners
                setActiveInput();
                setupNumberKeys();
                setupClearButton();
                setupEnterButton();
                setupCancelButton();
                setupBillCollection();
                setupReceiptPrinting();
            }
        });
    </script>
</body>

</html>