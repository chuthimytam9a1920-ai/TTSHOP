(function () {
    'use strict';

    var messages = {
        personFirst: 'Họ không hợp lệ.',
        personLast: 'Tên không hợp lệ.',
        phoneInvalid: 'Số điện thoại không hợp lệ. Vui lòng nhập đúng.',
        phoneTaken: 'Số điện thoại này đã được sử dụng.',
        emailInvalid: 'Email không hợp lệ.',
        otpRequired: 'Vui lòng nhập mã xác minh.',
        otpFormat: 'Mã xác minh phải gồm 6 chữ số.',
        emailCompanyInvalid: 'Email công ty không hợp lệ.',
        emailCompanySame: 'Email công ty phải khác email cá nhân.',
        passwordRequired: 'Vui lòng nhập mật khẩu.',
        passwordMin: 'Mật khẩu phải có ít nhất 6 ký tự.',
        passwordMax: 'Mật khẩu không được quá 64 ký tự.',
        passwordWhitespace: 'Mật khẩu không được chứa khoảng trắng.',
        passwordConfirm: 'Mật khẩu nhập lại không khớp.',
        passwordWarningLength: 'Nên dùng ít nhất 8 ký tự.',
        passwordWarningUppercase: 'Mật khẩu thiếu chữ in hoa (A-Z).',
        passwordWarningLowercase: 'Mật khẩu thiếu chữ thường (a-z).',
        passwordWarningNumber: 'Mật khẩu thiếu chữ số (0-9).',
        passwordWarningSpecial: 'Mật khẩu thiếu ký tự đặc biệt (ví dụ @ # $ !).',
        passwordWarningEmail: 'Mật khẩu chứa phần đầu email của bạn.',
        passwordWarningPhone: 'Mật khẩu chứa số điện thoại của bạn.',
        passwordWarningName: 'Mật khẩu chứa họ tên của bạn.',
        passwordWarningCommon: 'Mật khẩu này quá phổ biến, dễ bị đoán ra.',
        passwordPopupTitle: 'Mật khẩu của bạn còn yếu',
        passwordPopupFooter: 'Mật khẩu yếu dễ bị đoán ra. Bạn nên đặt mật khẩu mạnh hơn để bảo vệ tài khoản.',
        passwordChangeButton: 'Đổi mật khẩu',
        passwordContinueButton: 'Vẫn tiếp tục',
        companyName: 'Tên công ty không hợp lệ.',
        taxCode: 'Mã số thuế không hợp lệ.',
        companyAddress: 'Địa chỉ công ty không hợp lệ.',
        required: 'Vui lòng nhập thông tin này.',
        selectRequired: 'Vui lòng chọn một mục.',
        recipientRequired: 'Vui lòng nhập họ tên người nhận.',
        addressRequired: 'Vui lòng nhập địa chỉ giao hàng.',
        locationsRequired: 'Vui lòng chọn đầy đủ địa điểm giao hàng.',
        system: 'Hệ thống đang bận, vui lòng thử lại sau.'
    };
    var commonPasswords = [
        '123456', '12345678', '123456789', '1234567890', 'password', 'password1',
        'password123', 'qwerty', 'qwerty123', 'abc12345', 'abc123456',
        '11111111', '00000000', '87654321', 'iloveyou', 'admin123',
        'welcome1', 'letmein1', 'monkey123', 'dragon123', 'football1',
        'sunshine1', 'princess1', 'trustno1', 'passw0rd', '654321',
        'matkhau', 'matkhau123', 'matkhau1234', 'dungroi123', 't12345678'
    ];

    var style = document.createElement('style');
    style.textContent = '.field-error{display:block;color:#c62828;font-size:.875rem;margin-top:4px}.password-warning{display:block;color:#b45309;font-size:.9rem;margin-top:4px}.password-warning-dialog{position:fixed;inset:0;z-index:10000;background:rgba(0,0,0,.55);display:flex;align-items:center;justify-content:center;padding:16px}.password-warning-dialog__box{max-width:520px;width:100%;background:#fff;border-radius:12px;padding:24px;color:#333;box-shadow:0 12px 40px rgba(0,0,0,.3)}.password-warning-dialog__box h2{margin:0 0 14px}.password-warning-dialog__box p{margin:8px 0;line-height:1.5}.password-warning-dialog__actions{display:flex;justify-content:flex-end;gap:10px;margin-top:20px}.password-warning-dialog__actions button{border:0;border-radius:6px;padding:10px 14px;cursor:pointer}.password-warning-dialog__continue{background:#15985d;color:#fff}.password-warning-dialog__change{background:#e5e7eb;color:#222}';
    document.head.appendChild(style);

    function normalizePhone(value) {
        var phone = String(value || '').replace(/[\s.\-]/g, '');
        if (phone.indexOf('+84') === 0) {
            return '0' + phone.slice(3);
        }
        if (phone.indexOf('84') === 0) {
            return '0' + phone.slice(2);
        }
        return phone;
    }

    function isValidPhone(value) {
        var phone = normalizePhone(value);
        if (!/^0(3[2-9]|5[25689]|7[06-9]|8[1-9]|9[0-9])[0-9]{7}$/.test(phone)) {
            return false;
        }
        if (/^([0-9])\1{6}$/.test(phone.slice(3))) {
            return false;
        }
        var increasing = true;
        var decreasing = true;
        for (var index = 1; index < phone.length; index += 1) {
            if (Number(phone[index]) !== Number(phone[index - 1]) + 1) {
                increasing = false;
            }
            if (Number(phone[index]) !== Number(phone[index - 1]) - 1) {
                decreasing = false;
            }
        }
        return !increasing && !decreasing;
    }

    function isPersonalEmail(value) {
        var email = String(value || '').trim().toLowerCase();
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)
            && /^(?=[a-z0-9.]*[a-z])[a-z0-9](?:[a-z0-9.]{4,28}[a-z0-9])@gmail\.com$/.test(email);
    }

    function validPersonName(value) {
        var name = String(value || '').trim().replace(/\s+/g, ' ');
        return name.length >= 1 && name.length <= 50 && /[\p{L}]/u.test(name)
            && /^[\p{L}\s'.-]+$/u.test(name);
    }

    function validCompanyName(value) {
        var name = String(value || '').trim();
        return name.length >= 5 && name.length <= 150 && /[\p{L}]/u.test(name);
    }

    function validCompanyAddress(value) {
        var address = String(value || '').trim();
        return address.length >= 10 && /[\p{L}]/u.test(address) && /[0-9,]/.test(address);
    }

    function isDisposableEmail(email) {
        var domain = String(email || '').toLowerCase().split('@').pop();
        return [
            'mailinator.com', 'tempmail.com', '10minutemail.com', 'guerrillamail.com',
            'yopmail.com', 'throwawaymail.com', 'getnada.com', 'trashmail.com',
            'maildrop.cc', 'temp-mail.org', 'dispostable.com', 'fakeinbox.com', 'mohmal.com'
        ].indexOf(domain) !== -1;
    }

    function passwordBlockingError(password) {
        if (password === '') {
            return messages.passwordRequired;
        }
        var length = Array.from(password).length;
        if (length < 6) {
            return messages.passwordMin;
        }
        if (length > 64) {
            return messages.passwordMax;
        }
        if (/\s/u.test(password)) {
            return messages.passwordWhitespace;
        }
        return '';
    }

    function passwordWarnings(password, email, phone, name) {
        if (!password) {
            return [];
        }
        var lower = password.toLocaleLowerCase();
        var localPart = String(email || '').split('@')[0].toLocaleLowerCase();
        var normalizedPhone = normalizePhone(phone);
        var nameParts = String(name || '').trim().toLocaleLowerCase().split(/\s+/).filter(function (part) {
            return part.length >= 3;
        });
        var hasName = nameParts.some(function (part) { return lower.indexOf(part) !== -1; });
        var warnings = [];
        if (Array.from(password).length < 8) {
            warnings.push(messages.passwordWarningLength);
        }
        if (!/\p{Lu}/u.test(password)) {
            warnings.push(messages.passwordWarningUppercase);
        }
        if (!/\p{Ll}/u.test(password)) {
            warnings.push(messages.passwordWarningLowercase);
        }
        if (!/[0-9]/.test(password)) {
            warnings.push(messages.passwordWarningNumber);
        }
        if (!/[\p{P}\p{S}]/u.test(password)) {
            warnings.push(messages.passwordWarningSpecial);
        }
        if (localPart && lower.indexOf(localPart) !== -1) {
            warnings.push(messages.passwordWarningEmail);
        }
        if (normalizedPhone && password.indexOf(normalizedPhone) !== -1) {
            warnings.push(messages.passwordWarningPhone);
        }
        if (hasName) {
            warnings.push(messages.passwordWarningName);
        }
        if (commonPasswords.indexOf(lower) !== -1) {
            warnings.push(messages.passwordWarningCommon);
        }
        return warnings;
    }

    function messageFor(input) {
        var name = input.name || '';
        var rawValue = String(input.value || '');
        var isPassword = name === 'register_password' || name === 'new_password';
        var isPasswordConfirmation = name === 'register_password_confirm' || name === 'confirm_password';
        var value = isPassword || isPasswordConfirmation ? rawValue : rawValue.trim();
        var customType = input.getAttribute('data-validate') || '';

        if (value === '') {
            if (!input.required) {
                return '';
            }
            if (name === 'ho_ten') {
                return messages.recipientRequired;
            }
            if (name === 'dia_chi') {
                return messages.addressRequired;
            }
            if (name === 'tinh_thanh' || name === 'quan_huyen' || name === 'phuong_xa') {
                return messages.locationsRequired;
            }
            if (customType === 'phone' || name === 'sdt') {
                return messages.phoneInvalid;
            }
            if (customType === 'otp-code' || name === 'otp_code') {
                return messages.otpRequired;
            }
            if (customType === 'personal-email' || name === 'register_email' || name === 'email') {
                return messages.emailInvalid;
            }
            if (customType === 'person-name' || name === 'ho' || name === 'ten') {
                return name === 'ho' ? messages.personFirst : messages.personLast;
            }
            if (customType === 'company-name' || name === 'ten_cong_ty') {
                return messages.companyName;
            }
            if (customType === 'tax-code' || name === 'ma_so_thue') {
                return messages.taxCode;
            }
            if (customType === 'company-address' || name === 'dia_chi_cty') {
                return messages.companyAddress;
            }
            if (customType === 'company-email' || name === 'email_cty') {
                return messages.emailCompanyInvalid;
            }
            if (customType === 'strong-password' || isPassword) {
                return passwordBlockingError(rawValue);
            }
            if (customType === 'password-confirm' || isPasswordConfirmation) {
                return messages.passwordConfirm;
            }
            if (input.type === 'select-one') {
                return messages.selectRequired;
            }
            return messages.required;
        }
        if (customType === 'phone' || name === 'sdt') {
            return isValidPhone(value) ? '' : messages.phoneInvalid;
        }
        if (customType === 'otp-code' || name === 'otp_code') {
            return /^[0-9]{6}$/.test(value) ? '' : messages.otpFormat;
        }
        if (customType === 'personal-email' || name === 'register_email') {
            if (!isPersonalEmail(value)) {
                return messages.emailInvalid;
            }
            return '';
        }
        if (customType === 'person-name' || name === 'ho' || name === 'ten') {
            if (!validPersonName(value)) {
                return name === 'ho' ? messages.personFirst : messages.personLast;
            }
            return '';
        }
        if (customType === 'company-name' || name === 'ten_cong_ty') {
            return validCompanyName(value) ? '' : messages.companyName;
        }
        if (customType === 'tax-code' || name === 'ma_so_thue') {
            return /^[0-9]{10}(-[0-9]{3})?$/.test(value) ? '' : messages.taxCode;
        }
        if (customType === 'company-address' || name === 'dia_chi_cty') {
            return validCompanyAddress(value) ? '' : messages.companyAddress;
        }
        if (customType === 'company-email' || name === 'email_cty') {
            var personalEmail = input.form.querySelector('[name="register_email"]');
            var email = value.toLowerCase();
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                return messages.emailCompanyInvalid;
            }
            if (personalEmail && email === personalEmail.value.trim().toLowerCase()) {
                return messages.emailCompanySame;
            }
            return isDisposableEmail(email) ? messages.emailCompanyInvalid : '';
        }
        if (customType === 'strong-password' || isPassword) {
            return passwordBlockingError(rawValue);
        }
        if (customType === 'password-confirm' || isPasswordConfirmation) {
            var passwordField = input.form.querySelector('[name="register_password"], [name="new_password"]');
            return passwordField && rawValue === passwordField.value ? '' : messages.passwordConfirm;
        }
        if (input.type === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
            return messages.emailInvalid;
        }
        if (input.type === 'select-one' && input.required && !value) {
            return messages.selectRequired;
        }
        return '';
    }

    function errorElement(input) {
        var id = input.id || input.name || 'field';
        var passwordWrap = input.closest('.password-input-wrap');
        var fieldGroup = input.closest('.form-group') || input.parentNode;
        var candidates = fieldGroup.querySelectorAll('[data-error-for]');
        var error = null;
        Array.prototype.some.call(candidates, function (candidate) {
            var fieldName = candidate.getAttribute('data-error-for');
            if (fieldName === id || fieldName === input.name) {
                error = candidate;
                return true;
            }
            return false;
        });
        if (!error) {
            error = document.createElement('small');
            error.className = 'field-error';
            error.setAttribute('data-error-for', id);
            (passwordWrap || input).insertAdjacentElement('afterend', error);
        }
        return error;
    }

    function updateError(input) {
        var error = errorElement(input);
        var message = messageFor(input);
        error.textContent = message;
        input.setAttribute('aria-invalid', message ? 'true' : 'false');
        return !message;
    }

    function passwordDetails(input) {
        var form = input.form;
        var emailInput = form.querySelector('[name="register_email"], [name="email"]');
        var phoneInput = form.querySelector('[name="sdt"]');
        var firstName = form.querySelector('[name="ho"]');
        var lastName = form.querySelector('[name="ten"]');
        return {
            email: emailInput ? emailInput.value : (form.dataset.passwordEmail || ''),
            phone: phoneInput ? phoneInput.value : (form.dataset.passwordPhone || ''),
            name: [firstName && firstName.value, lastName && lastName.value].filter(Boolean).join(' ')
                || form.dataset.passwordName
                || ''
        };
    }

    function updatePasswordWarnings(input) {
        var container = input.form.querySelector('[data-password-warnings]');
        if (!container) {
            return [];
        }
        var details = passwordDetails(input);
        var warnings = passwordWarnings(input.value, details.email, details.phone, details.name);
        container.replaceChildren();
        warnings.forEach(function (warning) {
            var line = document.createElement('div');
            line.className = 'password-warning';
            line.textContent = warning;
            container.appendChild(line);
        });
        return warnings;
    }

    function showPasswordWarningDialog(warnings) {
        if (window.Swal && typeof window.Swal.fire === 'function') {
            var warningLines = warnings.map(function (warning) {
                return '<div>' + warning + '</div>';
            }).join('');
            return window.Swal.fire({
                title: messages.passwordPopupTitle,
                html: '<div style="text-align:left">' + warningLines + '<p style="margin-top:12px">' + messages.passwordPopupFooter + '</p></div>',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: messages.passwordContinueButton,
                cancelButtonText: messages.passwordChangeButton,
                focusCancel: true,
                reverseButtons: true
            });
        }

        return new Promise(function (resolve) {
            var overlay = document.createElement('div');
            overlay.className = 'password-warning-dialog';
            var box = document.createElement('div');
            box.className = 'password-warning-dialog__box';
            var title = document.createElement('h2');
            title.textContent = messages.passwordPopupTitle;
            box.appendChild(title);
            warnings.forEach(function (warning) {
                var line = document.createElement('p');
                line.textContent = warning;
                box.appendChild(line);
            });
            var footer = document.createElement('p');
            footer.textContent = messages.passwordPopupFooter;
            box.appendChild(footer);
            var actions = document.createElement('div');
            actions.className = 'password-warning-dialog__actions';
            var changeButton = document.createElement('button');
            changeButton.className = 'password-warning-dialog__change';
            changeButton.textContent = messages.passwordChangeButton;
            var continueButton = document.createElement('button');
            continueButton.className = 'password-warning-dialog__continue';
            continueButton.textContent = messages.passwordContinueButton;
            changeButton.addEventListener('click', function () {
                overlay.remove();
                resolve({ isConfirmed: false });
            });
            continueButton.addEventListener('click', function () {
                overlay.remove();
                resolve({ isConfirmed: true });
            });
            actions.appendChild(changeButton);
            actions.appendChild(continueButton);
            box.appendChild(actions);
            overlay.appendChild(box);
            document.body.appendChild(overlay);
            changeButton.focus();
        });
    }

    function checkRegistrationPhone(input) {
        if (input.form.action.indexOf('xulydangky.php') === -1 || !isValidPhone(input.value)) {
            return;
        }

        var checkedPhone = normalizePhone(input.value);
        fetch('kiem_tra_sdt.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: 'sdt=' + encodeURIComponent(checkedPhone)
        }).then(function (response) {
            if (!response.ok) {
                throw new Error('Không thể kiểm tra số điện thoại.');
            }
            return response.json();
        }).then(function (result) {
            if (normalizePhone(input.value) !== checkedPhone) {
                return;
            }
            var error = errorElement(input);
            error.textContent = result.trung ? messages.phoneTaken : '';
            input.setAttribute('aria-invalid', result.trung ? 'true' : 'false');
        }).catch(function () {
            if (normalizePhone(input.value) === checkedPhone) {
                var error = errorElement(input);
                error.textContent = messages.system;
                input.setAttribute('aria-invalid', 'true');
            }
        });
    }

    function bindPasswordToggles() {
        document.querySelectorAll('[data-password-toggle]').forEach(function (button) {
            button.addEventListener('click', function () {
                var inputId = button.getAttribute('aria-controls');
                var input = inputId ? document.getElementById(inputId) : null;
                if (!input || input.type !== 'password' && input.type !== 'text') {
                    return;
                }

                var isVisible = input.type === 'password';
                input.type = isVisible ? 'text' : 'password';
                button.setAttribute('aria-label', isVisible ? 'Ẩn mật khẩu' : 'Hiện mật khẩu');
                button.setAttribute('aria-pressed', isVisible ? 'true' : 'false');
                var icon = button.querySelector('i');
                if (icon) {
                    icon.classList.toggle('fa-eye', !isVisible);
                    icon.classList.toggle('fa-eye-slash', isVisible);
                }
            });
        });
    }

    function validateForm(form) {
        var valid = true;
        var fields = form.querySelectorAll('input, select, textarea');
        Array.prototype.forEach.call(fields, function (input) {
            if (input.disabled || input.type === 'hidden' || input.type === 'submit' || input.type === 'button'
                || input.closest('#uu-tien-fields') && window.getComputedStyle(input.closest('#uu-tien-fields')).display === 'none') {
                return;
            }
            if (!updateError(input)) {
                valid = false;
            }
        });
        return valid;
    }

    function bind() {
        bindPasswordToggles();
        document.querySelectorAll('form').forEach(function (form) {
            form.noValidate = true;
            form.addEventListener('input', function (event) {
                if (event.target.matches('input, select, textarea')) {
                    var confirmationInput = event.target.name === 'register_password_confirm' || event.target.name === 'confirm_password';
                    if (!confirmationInput) {
                        updateError(event.target);
                    }
                    if (event.target.name === 'register_password' || event.target.name === 'new_password') {
                        updatePasswordWarnings(event.target);
                    }
                    if (event.target.name === 'register_email' || event.target.name === 'email' || event.target.name === 'sdt' || event.target.name === 'ho' || event.target.name === 'ten') {
                        var password = form.querySelector('[name="register_password"], [name="new_password"]');
                        if (password) {
                            updatePasswordWarnings(password);
                            if (password.value) {
                                updateError(password);
                            }
                        }
                    }
                }
            });
            form.addEventListener('blur', function (event) {
                if (event.target.matches('input, select, textarea')) {
                    updateError(event.target);
                    if (event.target.name === 'sdt') {
                        checkRegistrationPhone(event.target);
                    }
                }
            }, true);
            form.addEventListener('submit', function (event) {
                if (!validateForm(form)) {
                    event.preventDefault();
                    var firstInvalid = form.querySelector('[aria-invalid="true"]');
                    if (firstInvalid) {
                        firstInvalid.focus();
                    }
                    return;
                }
                var passwordInput = form.querySelector('[name="register_password"], [name="new_password"]');
                if (passwordInput && form.dataset.passwordWarningsConfirmed !== 'true') {
                    var warnings = updatePasswordWarnings(passwordInput);
                    if (warnings.length > 0) {
                        event.preventDefault();
                        showPasswordWarningDialog(warnings).then(function (result) {
                            if (result.isConfirmed) {
                                form.dataset.passwordWarningsConfirmed = 'true';
                                if (typeof form.requestSubmit === 'function') {
                                    form.requestSubmit();
                                } else {
                                    form.submit();
                                }
                            } else {
                                passwordInput.focus();
                            }
                        });
                        return;
                    }
                }
                var button = form.querySelector('button[type="submit"], input[type="submit"]');
                if (button && form.action.indexOf('xulydangky.php') !== -1) {
                    button.disabled = true;
                    button.textContent = 'Đang gửi...';
                }
            });
        });

    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bind);
    } else {
        bind();
    }

    window.TTShopValidation = {
        normalizePhone: normalizePhone,
        isValidPhone: isValidPhone
    };
}());
