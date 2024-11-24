import type { ColumnFiltersState } from '@tanstack/react-table';
import { type FC } from 'react';
import { useTranslation } from 'react-i18next';
import { LuDices } from 'react-icons/lu';
import type { RouteName } from 'ziggy-js';

import { BaseButton } from '@/common/components/+vendor/BaseButton';
import { usePageProps } from '@/common/hooks/usePageProps';
import { cn } from '@/utils/cn';

import { useRandomGameId } from './useRandomGameId';

interface RandomGameButtonProps {
  columnFilters: ColumnFiltersState;
  variant: 'mobile-drawer' | 'toolbar';

  apiRouteName?: RouteName;
}

export const RandomGameButton: FC<RandomGameButtonProps> = ({
  columnFilters,
  variant,
  apiRouteName = 'api.game.random',
}) => {
  const {
    ziggy: { device },
  } = usePageProps();

  const { t } = useTranslation();

  const { getRandomGameId, prefetchRandomGameId } = useRandomGameId({
    apiRouteName,
    columnFilters,
  });

  const navigateToGame = (gameId: number) => {
    if (device === 'desktop') {
      window.open(route('game.show', gameId), '_blank');
    } else {
      window.location.assign(route('game.show', gameId));
    }
  };

  const handleClick = async () => {
    const gameId = await getRandomGameId();

    prefetchRandomGameId({ shouldForce: variant === 'toolbar' });

    navigateToGame(gameId);
  };

  return (
    <BaseButton
      onClick={handleClick}
      onMouseEnter={() => prefetchRandomGameId()}
      size={variant === 'toolbar' ? 'sm' : undefined}
      className={variant === 'mobile-drawer' ? 'gap-1.5' : 'group gap-1'}
      variant={variant === 'mobile-drawer' ? 'secondary' : undefined}
    >
      <LuDices className="size-4 transition-transform duration-100 group-hover:rotate-12" />
      <span className={cn(device === 'desktop' ? 'hidden sm:inline md:hidden xl:inline' : '')}>
        {t('Surprise me')}
      </span>
    </BaseButton>
  );
};