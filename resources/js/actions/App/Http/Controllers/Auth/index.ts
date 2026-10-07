import AuthenticatedSessionController from './AuthenticatedSessionController';
import EmailVerificationNotificationController from './EmailVerificationNotificationController';
import EmailVerificationPromptController from './EmailVerificationPromptController';
import NewPasswordController from './NewPasswordController';
import PasswordResetLinkController from './PasswordResetLinkController';
import RegisteredUserController from './RegisteredUserController';
import VerifyEmailController from './VerifyEmailController';

const Auth = {
    AuthenticatedSessionController: Object.assign(
        AuthenticatedSessionController,
        AuthenticatedSessionController,
    ),
    RegisteredUserController: Object.assign(
        RegisteredUserController,
        RegisteredUserController,
    ),
    PasswordResetLinkController: Object.assign(
        PasswordResetLinkController,
        PasswordResetLinkController,
    ),
    NewPasswordController: Object.assign(
        NewPasswordController,
        NewPasswordController,
    ),
    EmailVerificationPromptController: Object.assign(
        EmailVerificationPromptController,
        EmailVerificationPromptController,
    ),
    VerifyEmailController: Object.assign(
        VerifyEmailController,
        VerifyEmailController,
    ),
    EmailVerificationNotificationController: Object.assign(
        EmailVerificationNotificationController,
        EmailVerificationNotificationController,
    ),
};

export default Auth;
