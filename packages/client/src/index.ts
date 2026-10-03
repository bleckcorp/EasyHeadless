export type EasyHeadlessHealth = {
  version: string;
  plugins: {
    acf: boolean;
    wpGraphql: boolean;
    yoast: boolean;
    forms: Record<string, {
      available: boolean;
      installed?: boolean;
      active?: boolean;
      loaded?: boolean;
      version?: string | null;
      message?: string;
    }>;
  };
  modules: EasyHeadlessCapabilities;
  updater: EasyHeadlessUpdaterStatus;
};

export type EasyHeadlessModuleStatus = {
  enabled: boolean;
  available: boolean;
  status: "ready" | "degraded" | "disabled";
  message: string;
};

export type EasyHeadlessCapabilities = Record<
  "core" | "portfolio" | "church" | "tutor" | "forms" | "updater",
  EasyHeadlessModuleStatus
>;

export type EasyHeadlessUpdaterStatus = {
  enabled: true;
  configured: boolean;
  available: boolean;
  status: "ready" | "degraded";
  message: string;
  currentVersion: string;
  latestVersion: string;
  updateAvailable: boolean;
  lastAttempt: {
    timestamp: string;
    requestedVersion: string;
    installedVersion: string;
    success: boolean | null;
    message: string;
  };
};

export type EasyHeadlessUpdateResult = {
  success: true;
  status: EasyHeadlessUpdaterStatus["lastAttempt"] & { success: true };
  updater: EasyHeadlessUpdaterStatus;
};

export type EasyHeadlessSeo = {
  title?: string;
  description?: string;
  canonical?: string;
  yoastHead?: string | null;
  raw?: Record<string, unknown>;
};

export type EasyHeadlessPost = {
  id: number;
  slug: string;
  path: string;
  type: string;
  title: string;
  content: string;
  excerpt: string;
  featuredImage: null | {
    id: number;
    url: string;
    alt: string;
  };
  acf: Record<string, unknown>;
  seo: EasyHeadlessSeo;
  modified: string;
};

export type EasyHeadlessSite = {
  name: string;
  description: string;
  url: string;
  frontendUrl: string;
  lmsUrl: string;
  portalLinks: {
    login: string;
    registration: string;
    dashboard: string;
    courses: string;
  };
  settings: Record<string, unknown>;
  church?: Record<string, unknown>;
  contact: {
    companyName: string;
    phone: string;
    email: string;
    address: string;
  };
  social: Array<{ label: string; url: string }>;
  collections: {
    services: EasyHeadlessPost[];
    testimonials: EasyHeadlessPost[];
    faqs: EasyHeadlessPost[];
    teamMembers: EasyHeadlessPost[];
    churchSettings?: EasyHeadlessPost[];
    sermons?: EasyHeadlessPost[];
    events?: EasyHeadlessPost[];
    ministries?: EasyHeadlessPost[];
    leaders?: EasyHeadlessPost[];
    serviceTimes?: EasyHeadlessPost[];
    policies?: EasyHeadlessPost[];
  };
  capabilities: EasyHeadlessCapabilities;
  health: EasyHeadlessHealth;
};

export type EasyHeadlessNavigationItem = {
  id: number;
  parentId: number;
  label: string;
  url: string;
  target: string;
  order: number;
};

export type EasyHeadlessPortfolioItem = {
  id: number;
  attachmentId: number;
  url: string;
  srcSet: string | false;
  sizes: string | false;
  width: number;
  height: number;
  alt: string;
  title: string;
  caption: string;
  category: string;
  order: number;
};

export type EasyHeadlessCourse = {
  id: number;
  slug: string;
  title: string;
  excerpt: string;
  image: null | { id: number; url: string; alt: string };
  categories: Array<{ id: number; name: string; slug: string }>;
  instructor: { id: number; name: string };
  rating: null | { average: number; count: number };
  duration: string | Record<string, unknown> | null;
  price: string | null;
  isFree: boolean;
  lmsUrl: string;
  modified: string;
};

export type EasyHeadlessCourseCollection = {
  items: EasyHeadlessCourse[];
  pagination: {
    page: number;
    perPage: number;
    total: number;
    totalPages: number;
  };
  source: "curated" | "latest" | "category" | "unavailable";
};

export type EasyHeadlessRoute = {
  type: string;
  slug: string;
  path: string;
  title: string;
  modified: string;
};

export type EasyHeadlessPostCollection = {
  items: EasyHeadlessPost[];
  pagination: {
    page: number;
    perPage: number;
    total: number;
    totalPages: number;
  };
};

export type EasyHeadlessFormSummary = {
  id: string;
  provider: string;
  title: string;
};

export type EasyHeadlessForm = EasyHeadlessFormSummary & {
  fields: Array<{
    name: string;
    label: string;
    type: string;
    required: boolean;
    options: unknown[];
  }>;
};

export type CollectionType =
  | "services"
  | "testimonials"
  | "faqs"
  | "teamMembers"
  | "churchSettings"
  | "sermons"
  | "events"
  | "ministries"
  | "leaders"
  | "serviceTimes"
  | "policies";

export type WritePostInput = {
  title?: string;
  content?: string;
  excerpt?: string;
  status?: "draft" | "pending" | "publish" | "private";
  acf?: Record<string, unknown>;
  /** Assign the story to an EasyHeadless publication issue; use 0 to unassign. */
  issueId?: number;
};

export type EasyHeadlessIssue = {
  id: number;
  slug: string;
  title: string;
  date: string;
  volume: string;
  cover: string;
  description: string;
  excerpt?: string;
  href: string;
  categories: Array<{ id: number; name: string; slug: string }>;
  stories: Array<{
    id: number;
    slug: string;
    title: string;
    excerpt: string;
    date: string;
    image: string;
    imageAlt: string;
    imageCaption: string;
    author: { id: number; name: string; slug: string };
    categories: Array<{ id: number; name: string; slug: string }>;
    access: "public" | "members";
    presentationStyle: "nation" | "new-yorker";
  }>;
};

export type WriteIssueInput = {
  title?: string;
  slug?: string;
  description?: string;
  /** Manually authored archive summary, independent of issue categories/body. */
  excerpt?: string;
  status?: "draft" | "pending" | "publish" | "private";
  /** Edition date, YYYY-MM-DD; empty string clears the override. */
  date?: string;
  volume?: string;
  /** Existing Media Library attachment ID; 0 clears the cover. */
  featuredImageId?: number;
  featuredStoryId?: number;
  /** Ordered linked articles. Does not modify the articles themselves. */
  storyIds?: number[];
};

export type EditableIssue = EasyHeadlessIssue & {
  status: NonNullable<WriteIssueInput["status"]> | "future";
  featuredImageId: number;
  featuredStoryId: number;
  storyIds: number[];
};

export type IssueStorySearchResult = {
  items: Array<{ id: number; slug: string; title: string; featuredImageId: number; featuredImage: string; issueId: number }>;
  pagination: { page: number; perPage: number; total: number; totalPages: number };
};

export type EasyHeadlessClientOptions = {
  apiUrl: string;
  apiKey?: string;
  fetchImpl?: typeof fetch;
};

export class EasyHeadlessClient {
  private readonly apiUrl: string;
  private readonly apiKey?: string;
  private readonly fetchImpl: typeof fetch;

  constructor(options: EasyHeadlessClientOptions) {
    this.apiUrl = options.apiUrl.replace(/\/$/, "");
    this.apiKey = options.apiKey;
    this.fetchImpl = options.fetchImpl || fetch;
  }

  health() {
    return this.request<EasyHeadlessHealth>("/health");
  }

  getSite() {
    return this.request<EasyHeadlessSite>("/site");
  }

  getCapabilities() {
    return this.request<EasyHeadlessCapabilities>("/capabilities");
  }

  getNavigation() {
    return this.request<{ items: EasyHeadlessNavigationItem[] }>("/navigation");
  }

  getPortfolio() {
    return this.request<{ items: EasyHeadlessPortfolioItem[] }>("/portfolio");
  }

  getUpdaterStatus() {
    return this.request<EasyHeadlessUpdaterStatus>("/updater");
  }

  checkUpdate() {
    return this.request<EasyHeadlessUpdaterStatus>("/updater/check", {
      method: "POST",
      auth: true,
    });
  }

  installUpdate() {
    return this.request<EasyHeadlessUpdateResult>("/updater/install", {
      method: "POST",
      auth: true,
    });
  }

  updatePortfolio(items: Array<Pick<EasyHeadlessPortfolioItem, "attachmentId" | "title" | "caption" | "alt" | "category">>) {
    return this.request<{ success: true; items: EasyHeadlessPortfolioItem[] }>("/portfolio", {
      method: "PATCH",
      body: JSON.stringify({ items }),
      auth: true,
    });
  }

  listCourses(options: { selected?: number[]; category?: string; page?: number; perPage?: number } = {}) {
    const params = new URLSearchParams();
    if (options.selected?.length) params.set("selected", options.selected.join(","));
    if (options.category) params.set("category", options.category);
    if (options.page) params.set("page", String(options.page));
    if (options.perPage) params.set("perPage", String(options.perPage));
    const query = params.toString();
    return this.request<EasyHeadlessCourseCollection>(`/courses${query ? `?${query}` : ""}`);
  }

  getCourse(slug: string) {
    return this.request<EasyHeadlessCourse>(`/courses/${encodeURIComponent(slug)}`);
  }

  getChurch() {
    return this.request<Record<string, unknown>>("/church");
  }

  listRoutes() {
    return this.request<EasyHeadlessRoute[]>("/routes");
  }

  getPage(slug: string) {
    return this.request<EasyHeadlessPost>(`/page?slug=${encodeURIComponent(slug)}`);
  }

  listPosts(page = 1, perPage = 10) {
    return this.request<EasyHeadlessPostCollection>(`/posts?page=${page}&perPage=${perPage}`);
  }

  getPost(slug: string) {
    return this.request<EasyHeadlessPost>(`/posts?slug=${encodeURIComponent(slug)}`);
  }

  listIssues(options: { category?: string; perPage?: number } = {}) {
    const params = new URLSearchParams();
    if (options.category) params.set("category", options.category);
    if (options.perPage) params.set("per_page", String(options.perPage));
    const query = params.toString();
    return this.request<EasyHeadlessIssue[]>(`/publication/issues${query ? `?${query}` : ""}`);
  }

  getIssue(slug: string) {
    return this.request<EasyHeadlessIssue>(`/publication/issues/${encodeURIComponent(slug)}`);
  }

  inspectIssue(id: number) {
    return this.request<EditableIssue>(`/publication/issues/id/${id}`, { auth: true });
  }

  createIssue(input: WriteIssueInput & { title: string }) {
    return this.request<EditableIssue>("/publication/issues", {
      method: "POST", body: JSON.stringify(input), auth: true,
    });
  }

  updateIssue(id: number, input: WriteIssueInput) {
    return this.request<EditableIssue>(`/publication/issues/id/${id}`, {
      method: "PATCH", body: JSON.stringify(input), auth: true,
    });
  }

  searchStories(search: string, page = 1, perPage = 20) {
    const params = new URLSearchParams({ search, page: String(page), perPage: String(perPage) });
    return this.request<IssueStorySearchResult>(`/publication/story-search?${params}`, { auth: true });
  }

  assignPostToIssue(postId: number, issueId: number) {
    return this.updatePost(postId, { issueId });
  }

  listSermons() {
    return this.request<EasyHeadlessPost[]>("/sermons");
  }

  getSermon(slug: string) {
    return this.request<EasyHeadlessPost>(`/sermons?slug=${encodeURIComponent(slug)}`);
  }

  listEvents() {
    return this.request<EasyHeadlessPost[]>("/events");
  }

  listMinistries() {
    return this.request<EasyHeadlessPost[]>("/ministries");
  }

  listLeaders() {
    return this.request<EasyHeadlessPost[]>("/leaders");
  }

  listServiceTimes() {
    return this.request<EasyHeadlessPost[]>("/service-times");
  }

  listPolicies() {
    return this.request<EasyHeadlessPost[]>("/policies");
  }

  getPolicy(slug: string) {
    return this.request<EasyHeadlessPost>(`/policies?slug=${encodeURIComponent(slug)}`);
  }

  listForms() {
    return this.request<EasyHeadlessFormSummary[]>("/forms");
  }

  getForm(id: string) {
    return this.request<EasyHeadlessForm>(`/forms/${encodeURIComponent(id)}`);
  }

  submitForm(id: string, fields: Record<string, unknown>) {
    return this.request<{
      success: boolean;
      provider: string;
      confirmation: { type: string; message: string };
    }>(`/forms/${encodeURIComponent(id)}/submit`, {
      method: "POST",
      body: JSON.stringify({ fields }),
    });
  }

  updateApprovedForms(ids: string[]) {
    return this.request<{
      success: true;
      ids: string[];
      forms: EasyHeadlessFormSummary[];
    }>("/forms/approved", {
      method: "PATCH",
      body: JSON.stringify({ ids }),
      auth: true,
    });
  }

  updateSettings(settings: Record<string, unknown>) {
    return this.request<EasyHeadlessSite>("/settings", {
      method: "POST",
      body: JSON.stringify({ settings }),
      auth: true,
    });
  }

  updateModules(modules: Partial<Record<"portfolio" | "church" | "tutor" | "forms", boolean>>) {
    return this.request<{
      success: boolean;
      modules: Record<string, boolean>;
      capabilities: EasyHeadlessCapabilities;
    }>("/modules", {
      method: "POST",
      body: JSON.stringify({ modules }),
      auth: true,
    });
  }

  createPost(input: WritePostInput) {
    return this.request<EasyHeadlessPost>("/posts", {
      method: "POST",
      body: JSON.stringify(input),
      auth: true,
    });
  }

  updatePost(id: number, input: WritePostInput) {
    return this.request<EasyHeadlessPost>(`/posts/${id}`, {
      method: "PATCH",
      body: JSON.stringify(input),
      auth: true,
    });
  }

  updatePageAcf(id: number, acf: Record<string, unknown>, previewOnly = false) {
    return this.request<EasyHeadlessPost>(`/pages/${id}/acf`, {
      method: "POST",
      body: JSON.stringify({ acf, previewOnly }),
      auth: true,
    });
  }

  createCollectionItem(type: CollectionType, input: WritePostInput) {
    return this.request<EasyHeadlessPost>(`/collections/${type}`, {
      method: "POST",
      body: JSON.stringify(input),
      auth: true,
    });
  }

  updateCollectionItem(type: CollectionType, id: number, input: WritePostInput) {
    return this.request<EasyHeadlessPost>(`/collections/${type}/${id}`, {
      method: "PATCH",
      body: JSON.stringify(input),
      auth: true,
    });
  }

  private async request<T>(
    path: string,
    options: RequestInit & { auth?: boolean } = {},
  ): Promise<T> {
    const headers = new Headers(options.headers);
    headers.set("Content-Type", "application/json");

    if (options.auth) {
      if (!this.apiKey) {
        throw new Error("EASYHEADLESS_API_KEY is required for write requests.");
      }

      headers.set("Authorization", `Bearer ${this.apiKey}`);
    }

    const response = await this.fetchImpl(`${this.apiUrl}${path}`, {
      ...options,
      headers,
    });

    if (!response.ok) {
      let detail = `${response.status} ${response.statusText}`;
      try {
        const body = (await response.json()) as { message?: string; code?: string };
        detail = [body.code, body.message].filter(Boolean).join(": ") || detail;
      } catch {
        // Keep status text when response is not JSON.
      }

      throw new Error(`EasyHeadless request failed: ${detail}`);
    }

    return response.json() as Promise<T>;
  }
}
