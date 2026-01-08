-- Database optimization for pagination performance
-- Run this SQL to add indexes that will significantly improve query speed

-- Add index on content type for faster category filtering
CREATE INDEX IF NOT EXISTS idx_content_type ON content(type);

-- Add composite index for ordering
CREATE INDEX IF NOT EXISTS idx_content_type_title ON content(type, title);

-- Add index on content_id for faster server lookups
CREATE INDEX IF NOT EXISTS idx_servers_content_id ON servers(content_id);

-- Add index on episode_id for faster server lookups
CREATE INDEX IF NOT EXISTS idx_servers_episode_id ON servers(episode_id);

-- Add index on content_id for faster season lookups
CREATE INDEX IF NOT EXISTS idx_seasons_content_id ON seasons(content_id);

-- Add index on season_id for faster episode lookups
CREATE INDEX IF NOT EXISTS idx_episodes_season_id ON episodes(season_id);

-- Add indexes for better sorting and filtering
CREATE INDEX IF NOT EXISTS idx_content_release_year ON content(release_year);
CREATE INDEX IF NOT EXISTS idx_content_rating ON content(rating);

-- Analyze tables to update statistics for query optimizer
ANALYZE content;
ANALYZE servers;
ANALYZE seasons;
ANALYZE episodes;
