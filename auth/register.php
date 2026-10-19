<?php
/**
 * auth/register.php — Multi-role Registration
 */
require_once dirname(__DIR__) . '/includes/init.php';

$errors = [];

// Fetch provinces for dropdown
$provinces = $pdo->query('SELECT id, name FROM provinces WHERE is_active = 1 ORDER BY name')->fetchAll();

$ROLE_SLUGS = [
    'shop'      => 'Shop (Retailer)',
    'company'   => 'Company (Supplier/Manufacturer)',
    'transport' => 'Transport (Bilty/Delivery)',
    'vendor'    => 'Vendor (Distributor)',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require($_POST['csrf_token'] ?? null);

    $regRole    = $_POST['register_role'] ?? 'shop';
    $email      = clean($_POST['email'] ?? '');
    $phone      = clean($_POST['phone'] ?? '');
    $password   = $_POST['password'] ?? '';
    $confirm    = $_POST['confirm_password'] ?? '';

    // Common validations
    if (!array_key_exists($regRole, $ROLE_SLUGS))  $errors[] = 'Invalid role selected.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid email address.';
    if (!preg_match('/^[0-9+\-\s]{10,20}$/', $phone)) $errors[] = 'Phone must be 10-20 characters (digits only).';
    if (strlen($password) < 8)                     $errors[] = 'Password must be at least 8 characters.';
    if (!preg_match('/[A-Z]/', $password))          $errors[] = 'Password must contain an uppercase letter.';
    if (!preg_match('/[0-9]/', $password))          $errors[] = 'Password must contain a number.';
    if ($password !== $confirm)                     $errors[] = 'Passwords do not match.';

    // Duplicate email
    if (empty($errors)) {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        if ($stmt->fetch()) $errors[] = 'This email is already registered.';
    }

    // Role-specific validation
    $roleData = [];
    if (empty($errors)) {
        switch ($regRole) {

            case 'shop':
                $shopName = clean($_POST['shop_name'] ?? '');
                $ownerName = clean($_POST['owner_name'] ?? '');
                $provinceId = (int)($_POST['province_id'] ?? 0);
                $city = clean($_POST['city'] ?? '');
                $address = clean($_POST['address'] ?? '');

                if ($shopName === '')  $errors[] = 'Shop name is required.';
                if ($ownerName === '') $errors[] = 'Owner name is required.';
                if ($provinceId <= 0)  $errors[] = 'Province is required.';
                if ($city === '')      $errors[] = 'City is required.';
                if ($address === '')   $errors[] = 'Address is required.';

                $roleData = compact('shopName', 'ownerName', 'provinceId', 'city', 'address');
                break;

            case 'company':
                $companyName = clean($_POST['company_name'] ?? '');
                $contactPerson = clean($_POST['contact_person'] ?? '');
                $provinceId = (int)($_POST['province_id'] ?? 0);
                $city = clean($_POST['city'] ?? '');
                $address = clean($_POST['address'] ?? '');
                $website = clean($_POST['website'] ?? '');

                if ($companyName === '')    $errors[] = 'Company name is required.';
                if ($contactPerson === '')  $errors[] = 'Contact person is required.';
                if ($provinceId <= 0)       $errors[] = 'Province is required.';
                if ($city === '')           $errors[] = 'City is required.';
                if ($address === '')        $errors[] = 'Address is required.';

                $roleData = compact('companyName', 'contactPerson', 'provinceId', 'city', 'address', 'website');
                break;

            case 'transport':
                $companyName = clean($_POST['company_name'] ?? '');
                $contactPerson = clean($_POST['contact_person'] ?? '');
                $city = clean($_POST['city'] ?? '');
                $address = clean($_POST['address'] ?? '');
                $vehicleTypes = clean($_POST['vehicle_types'] ?? '');
                $serviceAreas = clean($_POST['service_areas'] ?? '');

                if ($companyName === '')    $errors[] = 'Transport company name is required.';
                if ($contactPerson === '')  $errors[] = 'Contact person is required.';
                if ($city === '')           $errors[] = 'City is required.';
                if ($address === '')        $errors[] = 'Address is required.';
                if ($vehicleTypes === '')   $errors[] = 'Vehicle types required (e.g. Truck, Mazda).';
                if ($serviceAreas === '')   $errors[] = 'Service areas required (e.g. Punjab, AJK).';

                $roleData = compact('companyName', 'contactPerson', 'city', 'address', 'vehicleTypes', 'serviceAreas');
                break;

            case 'vendor':
                $businessName = clean($_POST['business_name'] ?? '');
                $fullName     = clean($_POST['full_name'] ?? '');
                $address      = clean($_POST['address'] ?? '');

                if ($businessName === '')   $errors[] = 'Business name is required.';
                if ($fullName === '')       $errors[] = 'Full name is required.';
                if ($address === '')        $errors[] = 'Address is required.';

                $roleData = compact('businessName', 'fullName', 'address');
                break;
        }
    }

    // Insert
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $hash = password_hash($password, PASSWORD_DEFAULT);

            // Get role id
            $stmt = $pdo->prepare("SELECT id FROM roles WHERE name = ?");
            $stmt->execute([$regRole]);
            $roleId = (int)$stmt->fetchColumn();

            if (!$roleId) throw new Exception('Invalid role.');

            // Determine full_name
            $fullNameMap = [
                'shop'      => $roleData['ownerName'],
                'company'   => $roleData['contactPerson'],
                'transport' => $roleData['contactPerson'],
                'vendor'    => $roleData['fullName'],
            ];
            $userFullName = $fullNameMap[$regRole];

            // Insert user
            $stmt = $pdo->prepare(
                'INSERT INTO users (role_id, email, phone, password_hash, full_name, status)
                 VALUES (?, ?, ?, ?, ?, "pending")'
            );
            $stmt->execute([$roleId, $email, $phone, $hash, $userFullName]);
            $userId = (int)$pdo->lastInsertId();

            // Role-specific profile insert
            switch ($regRole) {
                case 'shop':
                    $stmt = $pdo->prepare(
                        'INSERT INTO shops (user_id, shop_name, owner_name, address, province_id)
                         VALUES (?, ?, ?, ?, ?)'
                    );
                    $stmt->execute([
                        $userId, $roleData['shopName'], $roleData['ownerName'],
                        $roleData['address'] . ', ' . $roleData['city'], $roleData['provinceId']
                    ]);
                    break;

                case 'company':
                    $stmt = $pdo->prepare(
                        'INSERT INTO companies (user_id, company_name, contact_person, address, province_id, website)
                         VALUES (?, ?, ?, ?, ?, ?)'
                    );
                    $stmt->execute([
                        $userId, $roleData['companyName'], $roleData['contactPerson'],
                        $roleData['address'] . ', ' . $roleData['city'], $roleData['provinceId'], $roleData['website']
                    ]);
                    break;

                case 'transport':
                    $stmt = $pdo->prepare(
                        'INSERT INTO transport_companies (user_id, company_name, contact_person, address, vehicle_types, service_areas)
                         VALUES (?, ?, ?, ?, ?, ?)'
                    );
                    $stmt->execute([
                        $userId, $roleData['companyName'], $roleData['contactPerson'],
                        $roleData['address'] . ', ' . $roleData['city'],
                        $roleData['vehicleTypes'], $roleData['serviceAreas']
                    ]);
                    break;

                case 'vendor':
                    $stmt = $pdo->prepare(
                        'INSERT INTO vendors (user_id, business_name, address)
                         VALUES (?, ?, ?)'
                    );
                    $stmt->execute([$userId, $roleData['businessName'], $roleData['address']]);
                    break;
            }

            $pdo->commit();

            log_activity($pdo, $regRole . '_registered', 'auth', $userId, 'New ' . $regRole . ' registered: ' . $email);

            flash_success('Registration successful! Your account is pending admin approval.');
            redirect(BASE_URL . 'auth/login.php');

        } catch (Throwable $ex) {
            $pdo->rollBack();
            error_log('Register error: ' . $ex->getMessage());
            $errors[] = 'Registration failed. Please try again.';
        }
    }
}

$pageTitle = 'Register';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<style>
    /* =========================================
       PREMIUM DARK REGISTER PAGE CSS
    ========================================= */
    body {
        background-color: #030305;
        color: #ffffff;
        margin: 0;
        overflow-x: hidden;
        font-family: 'Inter', system-ui, -apple-system, sans-serif;
    }

    /* Animated Glowing Orbs Background */
    .aurora-bg {
        position: fixed;
        top: 0;
        left: 0;
        width: 100vw;
        height: 100vh;
        z-index: -1;
        overflow: hidden;
    }
    .orb-1 {
        position: absolute;
        top: -10%; left: -10%;
        width: 50vw; height: 50vw;
        background: radial-gradient(circle, rgba(76, 29, 149, 0.3) 0%, transparent 60%);
        filter: blur(60px);
        animation: floatOrb 10s infinite alternate;
    }
    .orb-2 {
        position: absolute;
        bottom: -10%; right: -10%;
        width: 40vw; height: 40vw;
        background: radial-gradient(circle, rgba(59, 130, 246, 0.25) 0%, transparent 60%);
        filter: blur(60px);
        animation: floatOrb 12s infinite alternate-reverse;
    }

    @keyframes floatOrb {
        0% { transform: translate(0, 0) scale(1); }
        100% { transform: translate(5%, 5%) scale(1.1); }
    }

    /* Main Glass Card */
    .glass-card {
        background: rgba(15, 23, 42, 0.4);
        backdrop-filter: blur(24px);
        -webkit-backdrop-filter: blur(24px);
        border: 1px solid rgba(255, 255, 255, 0.08);
        border-radius: 24px;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        padding: 40px;
        margin-top: 5vh;
        margin-bottom: 5vh;
        opacity: 0;
        animation: popIn 0.7s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
    }

    @keyframes popIn {
        0% { opacity: 0; transform: scale(0.95) translateY(20px); }
        100% { opacity: 1; transform: scale(1) translateY(0); }
    }

    /* Role Cards */
    .role-card {
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 16px;
        padding: 20px 15px;
        text-align: center;
        cursor: pointer;
        transition: all 0.3s ease;
        background: rgba(255, 255, 255, 0.03);
        height: 100%;
        position: relative;
        overflow: hidden;
    }
    .role-card:hover { 
        border-color: rgba(167, 139, 250, 0.5); 
        transform: translateY(-3px); 
        background: rgba(255, 255, 255, 0.05);
    }
    .role-card.active { 
        border-color: #a855f7; 
        background: rgba(168, 85, 247, 0.1); 
        box-shadow: 0 0 20px rgba(168, 85, 247, 0.2); 
    }
    .role-card input[type="radio"] { display: none; }
    .role-card i { 
        font-size: 2.2rem; 
        color: #94a3b8; 
        transition: color 0.3s;
    }
    .role-card.active i { color: #a855f7; }
    .role-card .title { font-weight: 700; font-size: 0.95rem; margin-top: 10px; color: #fff; }
    .role-card .desc { font-size: 0.75rem; color: #64748b; margin-top: 2px; }

    /* Section Headers */
    .section-title {
        color: #cbd5e1 !important;
        font-weight: 700;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        padding-bottom: 10px;
        margin-bottom: 20px;
        font-size: 0.95rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    /* Inputs */
    .form-label {
        color: #94a3b8;
        font-size: 0.85rem;
        font-weight: 500;
    }
    .form-control, .form-select {
        background-color: rgba(255, 255, 255, 0.03) !important;
        border: 1px solid rgba(255, 255, 255, 0.1) !important;
        color: #ffffff !important;
        border-radius: 12px;
        padding: 12px 18px;
        transition: all 0.3s ease;
    }
    .form-control:focus, .form-select:focus {
        background-color: rgba(255, 255, 255, 0.06) !important;
        border-color: #a78bfa !important;
        box-shadow: 0 0 0 4px rgba(167, 139, 250, 0.1) !important;
    }
    .form-select option {
        background-color: #0f172a;
        color: #ffffff;
    }

    /* Autofill Fix */
    input:-webkit-autofill,
    input:-webkit-autofill:hover, 
    input:-webkit-autofill:focus, 
    input:-webkit-autofill:active {
        -webkit-box-shadow: 0 0 0 1000px #0f172a inset !important;
        -webkit-text-fill-color: #ffffff !important;
        transition: background-color 5000s ease-in-out 0s;
    }

    /* Password Toggle */
    .password-wrap { position: relative; }
    .password-wrap .toggle-pw {
        position: absolute; right: 15px; top: 50%; transform: translateY(-50%);
        background: none; border: none; color: #64748b; cursor: pointer;
        padding: 4px 8px; font-size: 1.1rem;
        transition: color 0.3s;
    }
    .password-wrap .toggle-pw:hover { color: #a78bfa; }

    /* Strength Bar */
    .strength-bar { height: 5px; border-radius: 5px; background: rgba(255, 255, 255, 0.1); margin-top: 8px; overflow: hidden; }
    .strength-bar > div { height: 100%; transition: all .3s; width: 0; }
    .strength-text { font-size: .75rem; margin-top: 6px; font-weight: 500; }

    /* Errors */
    .field-error { color: #f87171; font-size: .75rem; margin-top: 4px; display: none; }
    .field-error.show { display: block; }
    .form-control.is-invalid, .form-select.is-invalid { border-color: #f87171 !important; }
    .form-control.is-valid, .form-select.is-valid { border-color: #34d399 !important; }
    
    .alert-danger {
        background: rgba(220, 53, 69, 0.15);
        border: 1px solid rgba(220, 53, 69, 0.3);
        color: #f87171;
        border-radius: 12px;
    }

    /* Premium Button */
    .btn-premium {
        background: linear-gradient(135deg, #6366f1 0%, #a855f7 100%);
        border: none;
        color: #fff;
        box-shadow: 0 10px 20px rgba(99, 102, 241, 0.3);
        transition: all 0.3s ease;
        font-weight: 600;
        border-radius: 12px;
        padding: 12px 30px;
        position: relative;
        overflow: hidden;
    }
    .btn-premium::after {
        content: '';
        position: absolute;
        top: -50%; left: -50%;
        width: 200%; height: 200%;
        background: linear-gradient(to right, rgba(255,255,255,0) 0%, rgba(255,255,255,0.3) 50%, rgba(255,255,255,0) 100%);
        transform: rotate(45deg);
        transition: all 0.5s ease;
        opacity: 0;
    }
    .btn-premium:hover {
        transform: translateY(-2px);
        box-shadow: 0 15px 25px rgba(99, 102, 241, 0.5);
        color: #fff;
    }
    .btn-premium:hover::after { opacity: 1; left: 100%; }

    a.custom-link { color: #a78bfa; text-decoration: none; font-weight: 500; transition: color 0.3s; }
    a.custom-link:hover { color: #c4b5fd; text-decoration: underline; }
</style>

<div class="aurora-bg">
    <div class="orb-1"></div>
    <div class="orb-2"></div>
</div>

<div class="container py-4">
  <div class="row justify-content-center">
    <div class="col-lg-10">
      <div class="glass-card">

        <div class="text-center mb-4">
            <h3 class="fw-bold mb-2" style="color: #fff;">Create an Account</h3>
            <p style="color: #94a3b8; font-size: 0.95rem;">Select your role below to get started. Fields marked with <span class="text-danger">*</span> are required.</p>
        </div>

        <?php if (!empty($errors)): ?>
          <div class="alert alert-danger">
            <strong><i class="bi bi-exclamation-triangle"></i> Please fix the following:</strong>
            <ul class="mb-0 ps-3 mt-2">
              <?php foreach ($errors as $err): ?>
                <li><?= e($err) ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>

        <form method="post" id="registerForm" autocomplete="off" novalidate>
          <?= csrf_field() ?>

          <!-- ROLE SELECTOR -->
          <label class="form-label fw-bold mb-3" style="font-size: 1rem; color: #fff;">Register As: <span class="text-danger">*</span></label>
          <div class="row g-3 mb-5">
            <div class="col-6 col-md-3">
              <label class="role-card <?= (($_POST['register_role'] ?? 'shop') === 'shop') ? 'active' : '' ?>" data-role="shop">
                <input type="radio" name="register_role" value="shop" <?= (($_POST['register_role'] ?? 'shop') === 'shop') ? 'checked' : '' ?>>
                <i class="bi bi-shop"></i>
                <div class="title">Shop</div>
                <div class="desc">Retailer</div>
              </label>
            </div>
            <div class="col-6 col-md-3">
              <label class="role-card <?= (($_POST['register_role'] ?? '') === 'company') ? 'active' : '' ?>" data-role="company">
                <input type="radio" name="register_role" value="company" <?= (($_POST['register_role'] ?? '') === 'company') ? 'checked' : '' ?>>
                <i class="bi bi-building"></i>
                <div class="title">Company</div>
                <div class="desc">Supplier</div>
              </label>
            </div>
            <div class="col-6 col-md-3">
              <label class="role-card <?= (($_POST['register_role'] ?? '') === 'transport') ? 'active' : '' ?>" data-role="transport">
                <input type="radio" name="register_role" value="transport" <?= (($_POST['register_role'] ?? '') === 'transport') ? 'checked' : '' ?>>
                <i class="bi bi-truck"></i>
                <div class="title">Transport</div>
                <div class="desc">Bilty</div>
              </label>
            </div>
            <div class="col-6 col-md-3">
              <label class="role-card <?= (($_POST['register_role'] ?? '') === 'vendor') ? 'active' : '' ?>" data-role="vendor">
                <input type="radio" name="register_role" value="vendor" <?= (($_POST['register_role'] ?? '') === 'vendor') ? 'checked' : '' ?>>
                <i class="bi bi-person-badge"></i>
                <div class="title">Vendor</div>
                <div class="desc">Distributor</div>
              </label>
            </div>
          </div>

          <!-- COMMON FIELDS -->
          <h6 class="section-title">Account Information</h6>
          <div class="row g-3 mb-4">

            <div class="col-md-6">
              <label class="form-label">Email <span class="text-danger">*</span></label>
              <input type="email" name="email" id="email" class="form-control" required
                     placeholder="name@company.com"
                     value="<?= e($_POST['email'] ?? '') ?>">
              <div class="field-error" data-for="email">Please enter a valid email.</div>
            </div>

            <div class="col-md-6">
              <label class="form-label">Phone <span class="text-danger">*</span></label>
              <input type="text" name="phone" id="phone" class="form-control" required
                     placeholder="+92 300 1234567"
                     value="<?= e($_POST['phone'] ?? '') ?>">
              <div class="field-error" data-for="phone">Enter 10-20 digits (with + or - allowed).</div>
            </div>

            <div class="col-md-6">
              <label class="form-label">Password <span class="text-danger">*</span></label>
              <div class="password-wrap">
                <input type="password" name="password" id="password" class="form-control" required
                       placeholder="Min 8 chars, 1 uppercase, 1 number">
                <button type="button" class="toggle-pw" data-target="password"><i class="bi bi-eye"></i></button>
              </div>
              <div class="strength-bar"><div id="strengthBar"></div></div>
              <div class="strength-text" id="strengthText"></div>
              <div class="field-error" data-for="password">Min 8 chars, 1 uppercase, 1 number.</div>
            </div>

            <div class="col-md-6">
              <label class="form-label">Confirm Password <span class="text-danger">*</span></label>
              <div class="password-wrap">
                <input type="password" name="confirm_password" id="confirm_password" class="form-control" required placeholder="••••••••">
                <button type="button" class="toggle-pw" data-target="confirm_password"><i class="bi bi-eye"></i></button>
              </div>
              <div class="field-error" data-for="confirm_password">Passwords do not match.</div>
            </div>
          </div>

          <!-- DYNAMIC ROLE FIELDS -->

          <!-- SHOP FIELDS -->
          <div class="role-fields" data-role-fields="shop" style="display:none;">
            <h6 class="section-title mt-4">Shop Details</h6>
            <div class="row g-3 mb-4">
              <div class="col-md-6">
                <label class="form-label">Shop Name <span class="text-danger">*</span></label>
                <input type="text" name="shop_name" id="shop_name" class="form-control"
                       value="<?= e($_POST['shop_name'] ?? '') ?>">
                <div class="field-error" data-for="shop_name">Shop name is required.</div>
              </div>
              <div class="col-md-6">
                <label class="form-label">Owner Name <span class="text-danger">*</span></label>
                <input type="text" name="owner_name" id="owner_name" class="form-control"
                       value="<?= e($_POST['owner_name'] ?? '') ?>">
                <div class="field-error" data-for="owner_name">Owner name is required.</div>
              </div>
              <div class="col-md-6">
                <label class="form-label">Province / Region <span class="text-danger">*</span></label>
                <select name="province_id" id="province_id_shop" class="form-select">
                  <option value="">— Select —</option>
                  <?php foreach ($provinces as $p): ?>
                    <option value="<?= (int)$p['id'] ?>" <?= (($_POST['province_id'] ?? 0) == $p['id']) ? 'selected' : '' ?>>
                      <?= e($p['name']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <div class="field-error" data-for="province_id_shop">Select a province.</div>
              </div>
              <div class="col-md-6">
                <label class="form-label">City <span class="text-danger">*</span></label>
                <input type="text" name="city" id="city" class="form-control"
                       value="<?= e($_POST['city'] ?? '') ?>">
                <div class="field-error" data-for="city">City is required.</div>
              </div>
              <div class="col-12">
                <label class="form-label">Address <span class="text-danger">*</span></label>
                <input type="text" name="address" id="address" class="form-control"
                       value="<?= e($_POST['address'] ?? '') ?>">
                <div class="field-error" data-for="address">Address is required.</div>
              </div>
            </div>
          </div>

          <!-- COMPANY FIELDS -->
          <div class="role-fields" data-role-fields="company" style="display:none;">
            <h6 class="section-title mt-4">Company Details</h6>
            <div class="row g-3 mb-4">
              <div class="col-md-6">
                <label class="form-label">Company Name <span class="text-danger">*</span></label>
                <input type="text" name="company_name" id="company_name" class="form-control"
                       value="<?= e($_POST['company_name'] ?? '') ?>">
                <div class="field-error" data-for="company_name">Company name is required.</div>
              </div>
              <div class="col-md-6">
                <label class="form-label">Contact Person <span class="text-danger">*</span></label>
                <input type="text" name="contact_person" id="contact_person" class="form-control"
                       value="<?= e($_POST['contact_person'] ?? '') ?>">
                <div class="field-error" data-for="contact_person">Contact person is required.</div>
              </div>
              <div class="col-md-6">
                <label class="form-label">Province / Region <span class="text-danger">*</span></label>
                <select name="province_id" id="province_id_company" class="form-select">
                  <option value="">— Select —</option>
                  <?php foreach ($provinces as $p): ?>
                    <option value="<?= (int)$p['id'] ?>" <?= (($_POST['province_id'] ?? 0) == $p['id']) ? 'selected' : '' ?>>
                      <?= e($p['name']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <div class="field-error" data-for="province_id_company">Select a province.</div>
              </div>
              <div class="col-md-6">
                <label class="form-label">City <span class="text-danger">*</span></label>
                <input type="text" name="city" id="city_company" class="form-control"
                       value="<?= e($_POST['city'] ?? '') ?>">
                <div class="field-error" data-for="city_company">City is required.</div>
              </div>
              <div class="col-12">
                <label class="form-label">Address <span class="text-danger">*</span></label>
                <input type="text" name="address" id="address_company" class="form-control"
                       value="<?= e($_POST['address'] ?? '') ?>">
                <div class="field-error" data-for="address_company">Address is required.</div>
              </div>
              <div class="col-12">
                <label class="form-label">Website <small style="color: #64748b;">(optional)</small></label>
                <input type="text" name="website" class="form-control" placeholder="https://example.com"
                       value="<?= e($_POST['website'] ?? '') ?>">
              </div>
            </div>
          </div>

          <!-- TRANSPORT FIELDS -->
          <div class="role-fields" data-role-fields="transport" style="display:none;">
            <h6 class="section-title mt-4">Transport Details</h6>
            <div class="row g-3 mb-4">
              <div class="col-md-6">
                <label class="form-label">Company Name <span class="text-danger">*</span></label>
                <input type="text" name="company_name" id="company_name_tp" class="form-control"
                       value="<?= e($_POST['company_name'] ?? '') ?>">
                <div class="field-error" data-for="company_name_tp">Company name is required.</div>
              </div>
              <div class="col-md-6">
                <label class="form-label">Contact Person <span class="text-danger">*</span></label>
                <input type="text" name="contact_person" id="contact_person_tp" class="form-control"
                       value="<?= e($_POST['contact_person'] ?? '') ?>">
                <div class="field-error" data-for="contact_person_tp">Contact person is required.</div>
              </div>
              <div class="col-md-6">
                <label class="form-label">City <span class="text-danger">*</span></label>
                <input type="text" name="city" id="city_tp" class="form-control"
                       value="<?= e($_POST['city'] ?? '') ?>">
                <div class="field-error" data-for="city_tp">City is required.</div>
              </div>
              <div class="col-md-6">
                <label class="form-label">Address <span class="text-danger">*</span></label>
                <input type="text" name="address" id="address_tp" class="form-control"
                       value="<?= e($_POST['address'] ?? '') ?>">
                <div class="field-error" data-for="address_tp">Address is required.</div>
              </div>
              <div class="col-md-6">
                <label class="form-label">Vehicle Types <span class="text-danger">*</span></label>
                <input type="text" name="vehicle_types" id="vehicle_types" class="form-control"
                       placeholder="Truck, Mazda, Pickup"
                       value="<?= e($_POST['vehicle_types'] ?? '') ?>">
                <div class="field-error" data-for="vehicle_types">Vehicle types are required.</div>
              </div>
              <div class="col-md-6">
                <label class="form-label">Service Areas <span class="text-danger">*</span></label>
                <input type="text" name="service_areas" id="service_areas" class="form-control"
                       placeholder="Punjab, AJK"
                       value="<?= e($_POST['service_areas'] ?? '') ?>">
                <div class="field-error" data-for="service_areas">Service areas are required.</div>
              </div>
            </div>
          </div>

          <!-- VENDOR FIELDS -->
          <div class="role-fields" data-role-fields="vendor" style="display:none;">
            <h6 class="section-title mt-4">Vendor Details</h6>
            <div class="row g-3 mb-4">
              <div class="col-md-6">
                <label class="form-label">Business Name <span class="text-danger">*</span></label>
                <input type="text" name="business_name" id="business_name" class="form-control"
                       value="<?= e($_POST['business_name'] ?? '') ?>">
                <div class="field-error" data-for="business_name">Business name is required.</div>
              </div>
              <div class="col-md-6">
                <label class="form-label">Full Name <span class="text-danger">*</span></label>
                <input type="text" name="full_name" id="full_name" class="form-control"
                       value="<?= e($_POST['full_name'] ?? '') ?>">
                <div class="field-error" data-for="full_name">Full name is required.</div>
              </div>
              <div class="col-12">
                <label class="form-label">Address <span class="text-danger">*</span></label>
                <input type="text" name="address" id="address_vn" class="form-control"
                       value="<?= e($_POST['address'] ?? '') ?>">
                <div class="field-error" data-for="address_vn">Address is required.</div>
              </div>
            </div>
          </div>

          <div class="mt-5 d-flex justify-content-between align-items-center border-top pt-4" style="border-color: rgba(255,255,255,0.1) !important;">
            <a href="<?= BASE_URL ?>auth/login.php" class="custom-link small">Already have an account? <strong>Login</strong></a>
            <button type="submit" class="btn btn-premium">
              <i class="bi bi-check-circle me-1"></i> Register Now
            </button>
          </div>

        </form>

      </div>
    </div>
  </div>
</div>

<script>
// ============ ROLE SWITCHING ============
const roleCards = document.querySelectorAll('.role-card');
const roleFields = document.querySelectorAll('.role-fields');

function switchRole(role) {
    roleCards.forEach(c => c.classList.toggle('active', c.dataset.role === role));
    roleFields.forEach(f => f.style.display = (f.dataset.roleFields === role) ? '' : 'none');

    // Disable/enable inputs inside hidden role fields (so they don't submit empty required)
    roleFields.forEach(f => {
        const inputs = f.querySelectorAll('input, select, textarea');
        const isVisible = f.dataset.roleFields === role;
        inputs.forEach(i => {
            if (isVisible) {
                i.removeAttribute('disabled');
            } else {
                i.setAttribute('disabled', 'disabled');
            }
        });
    });
}

roleCards.forEach(card => {
    card.addEventListener('click', () => {
        const radio = card.querySelector('input[type=radio]');
        radio.checked = true;
        switchRole(card.dataset.role);
    });
});

// Init on load
const initialRole = document.querySelector('input[name="register_role"]:checked')?.value || 'shop';
switchRole(initialRole);

// ============ SHOW/HIDE PASSWORD ============
document.querySelectorAll('.toggle-pw').forEach(btn => {
    btn.addEventListener('click', () => {
        const target = document.getElementById(btn.dataset.target);
        const icon = btn.querySelector('i');
        if (target.type === 'password') {
            target.type = 'text';
            icon.classList.remove('bi-eye');
            icon.classList.add('bi-eye-slash');
        } else {
            target.type = 'password';
            icon.classList.remove('bi-eye-slash');
            icon.classList.add('bi-eye');
        }
    });
});

// ============ PASSWORD STRENGTH ============
const pwInput = document.getElementById('password');
const bar = document.getElementById('strengthBar');
const txt = document.getElementById('strengthText');

pwInput.addEventListener('input', () => {
    const pw = pwInput.value;
    let score = 0;
    if (pw.length >= 8) score++;
    if (/[A-Z]/.test(pw)) score++;
    if (/[0-9]/.test(pw)) score++;
    if (/[!@#$%^&*(),.?":{}|<>]/.test(pw)) score++;

    bar.className = '';
    if (pw.length === 0) {
        bar.style.width = '0'; txt.textContent = '';
    } else if (score <= 1) {
        bar.style.width = '33%'; bar.style.background = '#ef4444';
        txt.textContent = 'Weak password'; txt.style.color = '#ef4444';
    } else if (score === 2 || score === 3) {
        bar.style.width = '66%'; bar.style.background = '#f59e0b';
        txt.textContent = 'Medium password'; txt.style.color = '#f59e0b';
    } else {
        bar.style.width = '100%'; bar.style.background = '#10b981';
        txt.textContent = 'Strong password ✓'; txt.style.color = '#10b981';
    }
});

// ============ LIVE VALIDATION ============
function showError(fieldId, show) {
    const errorEl = document.querySelector(`.field-error[data-for="${fieldId}"]`);
    const inputEl = document.getElementById(fieldId);
    if (!errorEl || !inputEl) return;
    errorEl.classList.toggle('show', show);
    inputEl.classList.toggle('is-invalid', show);
    inputEl.classList.toggle('is-valid', !show && inputEl.value !== '');
}

// Email
document.getElementById('email')?.addEventListener('blur', function() {
    const ok = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(this.value);
    showError('email', !ok && this.value !== '');
});

// Phone
document.getElementById('phone')?.addEventListener('blur', function() {
    const ok = /^[0-9+\-\s]{10,20}$/.test(this.value);
    showError('phone', !ok && this.value !== '');
});

// Password
pwInput.addEventListener('blur', function() {
    const ok = this.value.length >= 8 && /[A-Z]/.test(this.value) && /[0-9]/.test(this.value);
    showError('password', !ok && this.value !== '');
});

// Confirm password
document.getElementById('confirm_password')?.addEventListener('input', function() {
    const ok = this.value === pwInput.value;
    showError('confirm_password', !ok && this.value !== '');
});

// ============ SUBMIT VALIDATION ============
document.getElementById('registerForm').addEventListener('submit', (e) => {
    const role = document.querySelector('input[name="register_role"]:checked')?.value;
    let hasError = false;

    // Common
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(document.getElementById('email').value)) hasError = true;
    if (!/^[0-9+\-\s]{10,20}$/.test(document.getElementById('phone').value)) hasError = true;
    const pw = document.getElementById('password').value;
    if (pw.length < 8 || !/[A-Z]/.test(pw) || !/[0-9]/.test(pw)) hasError = true;
    if (pw !== document.getElementById('confirm_password').value) hasError = true;

    // Role-specific required fields
    const requiredByRole = {
        shop:      ['shop_name', 'owner_name', 'province_id_shop', 'city', 'address'],
        company:   ['company_name', 'contact_person', 'province_id_company', 'city_company', 'address_company'],
        transport: ['company_name_tp', 'contact_person_tp', 'city_tp', 'address_tp', 'vehicle_types', 'service_areas'],
        vendor:    ['business_name', 'full_name', 'address_vn'],
    };

    (requiredByRole[role] || []).forEach(id => {
        const el = document.getElementById(id);
        if (el && !el.value.trim()) {
            hasError = true;
            showError(id, true);
        }
    });

    if (hasError) {
        e.preventDefault();
        // Scroll to top
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
});
</script>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>