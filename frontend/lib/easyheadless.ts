import type { Metadata } from "next";

const API_URL = process.env.NEXT_PUBLIC_EASYHEADLESS_API_URL;
const REVALIDATE_SECONDS = Number(process.env.EASYHEADLESS_REVALIDATE_SECONDS || 60);
const USE_MOCKS = process.env.EASYHEADLESS_USE_MOCKS === "true";

export type EasyHeadlessHealth = {
  version: string;
  plugins: {
    acf: boolean;
    wpGraphql: boolean;
    yoast: boolean;
    forms: Record<string, { available: boolean }>;
  };
};

export type EasyHeadlessSite = {
  name: string;
  description: string;
  url: string;
  settings: Record<string, unknown>;
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
  };
  health: EasyHeadlessHealth;
};

export type EasyHeadlessSeo = {
  title?: string;
  description?: string;
  canonical?: string;
  yoastHead?: string | null;
  raw?: Record<string, unknown>;
};

export type EasyHeadlessRoute = {
  type: "page" | "post";
  slug: string;
  path: string;
  title: string;
  modified: string;
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

export type FormSubmitResult =
  | {
      success: true;
      provider: string;
      confirmation?: {
        type: string;
        message: string;
      };
    }
  | {
      success: false;
      message?: string;
      errors?: Record<string, string>;
    };

function requireApiUrl() {
  if (!API_URL) {
    throw new Error("NEXT_PUBLIC_EASYHEADLESS_API_URL is not configured.");
  }

  return API_URL.replace(/\/$/, "");
}

async function request<T>(path: string, init?: RequestInit): Promise<T> {
  if (USE_MOCKS) {
    return mockRequest<T>(path, init);
  }

  const response = await fetch(`${requireApiUrl()}${path}`, {
    ...init,
    headers: {
      "Content-Type": "application/json",
      ...init?.headers,
    },
    next: init?.method && init.method !== "GET" ? undefined : { revalidate: REVALIDATE_SECONDS },
  });

  if (!response.ok) {
    throw new Error(`EasyHeadless request failed: ${response.status} ${response.statusText}`);
  }

  return response.json() as Promise<T>;
}

async function mockRequest<T>(path: string, init?: RequestInit): Promise<T> {
  if (path === "/site") {
    return mockSite as T;
  }

  if (path === "/routes") {
    return mockRoutes as T;
  }

  if (path.startsWith("/page")) {
    return mockPage as T;
  }

  if (path.startsWith("/posts?slug=")) {
    return mockPost as T;
  }

  if (path.startsWith("/posts")) {
    return {
      items: [mockPost],
      pagination: {
        page: 1,
        perPage: 10,
        total: 1,
        totalPages: 1,
      },
    } as T;
  }

  if (path === "/forms") {
    return [{ id: "mock:contact", provider: "mock", title: "Contact" }] as T;
  }

  if (path.startsWith("/forms/") && path.endsWith("/submit")) {
    return {
      success: true,
      provider: "mock",
      confirmation: {
        type: "message",
        message: "Thanks for contacting us.",
      },
    } as T;
  }

  if (path.startsWith("/forms/")) {
    return {
      id: "mock:contact",
      provider: "mock",
      title: "Contact",
      fields: [
        { name: "name", label: "Name", type: "input_text", required: true, options: [] },
        { name: "email", label: "Email", type: "input_email", required: true, options: [] },
        { name: "message", label: "Message", type: "textarea", required: true, options: [] },
      ],
    } as T;
  }

  if (init?.method === "POST") {
    return { success: true } as T;
  }

  throw new Error(`No EasyHeadless mock response for ${path}`);
}

export async function getSite() {
  return request<EasyHeadlessSite>("/site");
}

export async function getRoutes() {
  return request<EasyHeadlessRoute[]>("/routes");
}

export async function getPage(path: string) {
  try {
    return await request<EasyHeadlessPost>(`/page?slug=${encodeURIComponent(path)}`);
  } catch {
    return null;
  }
}

export async function getPosts(page = 1, perPage = 10) {
  return request<EasyHeadlessPostCollection>(`/posts?page=${page}&perPage=${perPage}`);
}

export async function getPost(slug: string) {
  try {
    return await request<EasyHeadlessPost>(`/posts?slug=${encodeURIComponent(slug)}`);
  } catch {
    return null;
  }
}

export async function getForms() {
  return request<EasyHeadlessFormSummary[]>("/forms");
}

export async function getForm(id: string) {
  return request<EasyHeadlessForm>(`/forms/${encodeURIComponent(id)}`);
}

export async function submitForm(id: string, fields: Record<string, string>): Promise<FormSubmitResult> {
  try {
    return await request<FormSubmitResult>(`/forms/${encodeURIComponent(id)}/submit`, {
      method: "POST",
      body: JSON.stringify({ fields }),
      cache: "no-store",
    });
  } catch (error) {
    return {
      success: false,
      message: error instanceof Error ? error.message : "Unable to submit this form.",
    };
  }
}

export function metadataFromSeo(seo: EasyHeadlessSeo | undefined, fallbackTitle: string): Metadata {
  return {
    title: seo?.title || fallbackTitle,
    description: seo?.description,
    alternates: seo?.canonical ? { canonical: seo.canonical } : undefined,
    openGraph: {
      title: seo?.title || fallbackTitle,
      description: seo?.description,
      url: seo?.canonical,
    },
    twitter: {
      card: "summary_large_image",
      title: seo?.title || fallbackTitle,
      description: seo?.description,
    },
  };
}

const mockRoutes: EasyHeadlessRoute[] = [
  {
    type: "page",
    slug: "home",
    path: "/",
    title: "EasyHeadless Demo",
    modified: new Date(0).toISOString(),
  },
  {
    type: "post",
    slug: "hello-headless",
    path: "/blog/hello-headless",
    title: "Hello Headless",
    modified: new Date(0).toISOString(),
  },
];

const mockPage: EasyHeadlessPost = {
  id: 1,
  slug: "home",
  path: "/",
  type: "page",
  title: "EasyHeadless Demo",
  content: "<p>This starter renders WordPress content through the EasyHeadless bridge.</p>",
  excerpt: "A fast Next.js starter backed by WordPress content.",
  featuredImage: null,
  acf: {
    sections: [
      {
        type: "services",
        heading: "Client-editable services",
        copy: "Use ACF presets in WordPress and render the frontend from normalized bridge data.",
        cta_label: "Read the docs",
        cta_url: "/blog/hello-headless",
      },
    ],
  },
  seo: {
    title: "EasyHeadless Demo",
    description: "A demo page for the EasyHeadless Next.js starter.",
    canonical: "/",
  },
  modified: new Date(0).toISOString(),
};

const mockPost: EasyHeadlessPost = {
  id: 2,
  slug: "hello-headless",
  path: "/blog/hello-headless",
  type: "post",
  title: "Hello Headless",
  content: "<p>Publish posts in WordPress and render them from the Next.js frontend.</p>",
  excerpt: "The starter includes typed post routes and SEO metadata.",
  featuredImage: null,
  acf: {},
  seo: {
    title: "Hello Headless",
    description: "A mock post for local EasyHeadless builds.",
    canonical: "/blog/hello-headless",
  },
  modified: new Date(0).toISOString(),
};

const mockSite: EasyHeadlessSite = {
  name: "EasyHeadless Demo",
  description: "WordPress content, Next.js frontend.",
  url: "https://example.com",
  settings: {},
  contact: {
    companyName: "EasyHeadless Demo",
    phone: "",
    email: "hello@example.com",
    address: "",
  },
  social: [],
  collections: {
    services: [],
    testimonials: [],
    faqs: [],
    teamMembers: [],
  },
  health: {
    version: "mock",
    plugins: {
      acf: true,
      wpGraphql: false,
      yoast: true,
      forms: {
        mock: {
          available: true,
        },
      },
    },
  },
};
