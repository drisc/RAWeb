import type { ColumnDef } from '@tanstack/react-table';
import dayjs from 'dayjs';
import localizedFormat from 'dayjs/plugin/localizedFormat';
import utc from 'dayjs/plugin/utc';
import type { RouteName } from 'ziggy-js';

import { formatDate } from '@/common/utils/l10n/formatDate';
import type { TranslatedString } from '@/types/i18next';

import { DataTableColumnHeader } from '../../components/DataTableColumnHeader';
import { gameListFieldIconMap } from '../gameListFieldIconMap';

dayjs.extend(utc);
dayjs.extend(localizedFormat);

interface BuildLastUpdatedColumnDefProps {
  t_label: TranslatedString;

  tableApiRouteName?: RouteName;
  tableApiRouteParams?: Record<string, unknown>;
}

export function buildLastUpdatedColumnDef({
  t_label,
  tableApiRouteParams,
  tableApiRouteName = 'api.game.index',
}: BuildLastUpdatedColumnDefProps): ColumnDef<App.Platform.Data.GameListEntry> {
  return {
    id: 'lastUpdated',
    accessorKey: 'game',
    meta: { t_label, sortType: 'date', Icon: gameListFieldIconMap.lastUpdated },

    header: ({ column, table }) => (
      <DataTableColumnHeader
        column={column}
        table={table}
        tableApiRouteName={tableApiRouteName}
        tableApiRouteParams={tableApiRouteParams}
      />
    ),

    cell: ({ row }) => {
      const date = row.original.game?.lastUpdated ?? new Date();

      return <p>{formatDate(dayjs.utc(date), 'll')}</p>;
    },
  };
}
