import { useMutation } from '@tanstack/react-query';
import axios from 'axios';
import { type FC } from 'react';
import { useTranslation } from 'react-i18next';
import { LuCircleAlert } from 'react-icons/lu';
import { route } from 'ziggy-js';

import { BaseButton } from '@/common/components/+vendor/BaseButton';
import { toastMessage } from '@/common/components/+vendor/BaseToaster';

export const ManageConnectApiKey: FC = () => {
  const { t } = useTranslation();

  const mutation = useMutation({
    mutationFn: () => {
      return axios.delete(route('api.settings.keys.connect.destroy'));
    },
  });

  const handleResetApiKeyClick = () => {
    if (
      !confirm(
        t(
          'Are you sure you want to reset your Connect API key? This will log you out of all emulators.',
        ),
      )
    ) {
      return;
    }

    toastMessage.promise(mutation.mutateAsync(), {
      loading: t('Resetting...'),
      success: t('Your Connect API key has been reset.'),
      error: t('Something went wrong.'),
    });
  };

  return (
    <div className="@container">
      <div className="flex flex-col @lg:grid @lg:grid-cols-4">
        <p className="w-48 text-menu-link">{t('Connect API Key')}</p>

        <div className="col-span-3 flex flex-col gap-2">
          <p>
            {t(
              'Your Connect API key is used by emulators to keep you logged in. Resetting the key will log you out of all emulators.',
            )}
          </p>

          <BaseButton
            className="flex w-full gap-2 @lg:max-w-fit"
            size="sm"
            variant="destructive"
            onClick={handleResetApiKeyClick}
          >
            <LuCircleAlert className="h-4 w-4" />
            {t('Reset Connect API Key')}
          </BaseButton>
        </div>
      </div>
    </div>
  );
};
