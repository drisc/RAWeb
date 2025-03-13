import { router } from '@inertiajs/react';
import type { FC } from 'react';

import { ForumBreadcrumbs } from '@/common/components/ForumBreadcrumbs';
import { FullPaginator } from '@/common/components/FullPaginator';
import { MutedMessage } from '@/common/components/MutedMessage';
import { SignInMessage } from '@/common/components/SignInMessage';
import { SubscribeToggleButton } from '@/common/components/SubscribeToggleButton';
import { usePageProps } from '@/common/hooks/usePageProps';
import { useShortcodeBodyPreview } from '@/common/hooks/useShortcodeBodyPreview';
import type { TranslatedString } from '@/types/i18next';

import { ForumPostCard } from '../ForumPostCard';
import { QuickReplyForm } from '../QuickReplyForm';
import { TopicOptions } from '../TopicOptions';

export const ShowForumTopicMainRoot: FC = () => {
  const { auth, can, forumTopic, isSubscribed, paginatedForumTopicComments, ziggy } =
    usePageProps<App.Data.ShowForumTopicPageProps>();

  const { initiatePreview, previewContent } = useShortcodeBodyPreview();

  const handlePageSelectValueChange = (newPageValue: number) => {
    router.visit(
      route('forum-topic.show', {
        topic: forumTopic.id,
        _query: { page: newPageValue },
      }),
    );
  };

  return (
    <div>
      <ForumBreadcrumbs
        forum={forumTopic.forum}
        forumCategory={forumTopic.forum!.category}
        t_currentPageLabel={forumTopic.title as TranslatedString}
      />
      <h1 className="text-h3 w-full self-end sm:mt-2.5 sm:!text-[2.0em]">{forumTopic.title}</h1>

      {can.updateForumTopic ? (
        <div className="mb-4 flex flex-col gap-2">
          <TopicOptions />
        </div>
      ) : null}

      <div className="flex items-center justify-between">
        <FullPaginator
          onPageSelectValueChange={handlePageSelectValueChange}
          paginatedData={paginatedForumTopicComments}
        />

        {auth ? (
          <SubscribeToggleButton
            subjectId={forumTopic.id}
            subjectType="ForumTopic"
            hasExistingSubscription={isSubscribed}
          />
        ) : null}
      </div>

      <div className="mt-2 flex flex-col gap-3">
        {paginatedForumTopicComments.items.map((comment) => (
          <ForumPostCard
            key={`comment-${comment.id}`}
            body={comment.body}
            canManage={can.manageForumTopicComments}
            canUpdate={can.manageForumTopicComments || getCanUpdatePost(comment, auth?.user)}
            comment={comment}
            isHighlighted={ziggy.query.comment === String(comment.id)}
            topic={forumTopic}
          />
        ))}
      </div>

      <div className="mt-4">
        <FullPaginator
          onPageSelectValueChange={handlePageSelectValueChange}
          paginatedData={paginatedForumTopicComments}
        />
      </div>

      <div>
        {auth?.user.isMuted && auth.user.mutedUntil ? (
          <MutedMessage mutedUntil={auth.user.mutedUntil} />
        ) : (
          <div className="mt-4">
            <QuickReplyForm onPreview={initiatePreview} />
          </div>
        )}

        {!auth?.user ? <SignInMessage /> : null}

        {previewContent ? (
          <div data-testid="preview-content" className="mb-3 mt-7">
            <ForumPostCard body={previewContent} />
          </div>
        ) : null}
      </div>
    </div>
  );
};

function getCanUpdatePost(post: App.Data.ForumTopicComment, user?: App.Data.User | null): boolean {
  if (!user || user.isMuted) {
    return false;
  }

  return user.displayName === post.user?.displayName;
}
