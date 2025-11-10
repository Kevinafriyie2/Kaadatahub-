<div class="kaa-auth-portal">
    <div class="auth-tabs">
        <button class="tab-link active" data-tab="login">Login</button>
        <button class="tab-link" data-tab="register">Register</button>
    </div>

    <div id="login" class="auth-tab-content active">
        <form id="kaa-login-form">
            <div class="kaa-mall-notice"></div>
            <p class="form-row">
                <label for="username">Username or Email <span class="required">*</span></label>
                <input type="text" name="username" id="username" autocomplete="username" required>
            </p>
            <p class="form-row">
                <label for="password">Password <span class="required">*</span></label>
                <input type="password" name="password" id="password" autocomplete="current-password" required>
            </p>
            <p class="form-row">
                <button type="submit" class="button">Log in</button>
            </p>
        </form>
    </div>

    <div id="register" class="auth-tab-content">
        <form id="kaa-register-form">
            <div class="kaa-mall-notice"></div>
            <p class="form-row">
                <label for="reg_email">Email address <span class="required">*</span></label>
                <input type="email" name="email" id="reg_email" autocomplete="email" required>
            </p>
            <p class="form-row">
                <label for="reg_password">Password <span class="required">*</span></label>
                <input type="password" name="password" id="reg_password" autocomplete="new-password" required>
            </p>
            <p class="form-row">
                <button type="submit" class="button">Register</button>
            </p>
        </form>
    </div>
</div>
