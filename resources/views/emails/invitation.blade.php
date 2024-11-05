<div style="font-family: Arial, sans-serif; background-color: #f3f4f6; padding: 20px; margin: auto; color: #333;">
    <!-- Header -->
    <div style="text-align: center; padding: 20px 0;">
        <h2 style="color: #333333; font-size: 28px; margin-bottom: 10px;">
            {{ __('You’re Invited to Join the :team Team!', ['team' => $invitation->team->name]) }}
        </h2>
        <p style="font-size: 16px; color: #666666; margin: 0;">
            {{ __('We are thrilled to have you with us! Follow the instructions below to join our team.') }}
        </p>
    </div>

    <!-- Invitation Info -->
    <p style="font-size: 15px; color: #555555; line-height: 1.7;">
        {{ __('If you already have an account or create one using the provided link, click below to accept the invitation and join the team!') }}
    </p>

    <!-- Accept Invitation Button -->
    <div style="margin-top: 20px; text-align: center;">
        <a href="{{ $url }}" style="display: inline-block; padding: 12px 28px; font-size: 16px; font-weight: bold; color: #ffffff; background-color: #28a745; border-radius: 6px; text-decoration: none; box-shadow: 0 4px 8px rgba(40, 167, 69, 0.3);">
            {{ __('Accept Invitation') }}
        </a>
    </div>

    <!-- Footer -->
    <div style="margin-top: 30px; text-align: center; font-size: 14px; color: #999999;">
        <p>{{ __('If you have any questions, feel free to reach out to our support team.') }}</p>
        <p>{{ __('Best regards,') }}<br><strong>{{ config('app.name') }}</strong></p>
    </div>
</div>