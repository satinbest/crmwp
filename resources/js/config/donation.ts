/**
 * Donation Configuration
 * Public developer donation details for CRMWP.
 * Maintained as an explicit source of truth in code, eliminating dependency on .env.
 */

export interface DonationConfig {
  recipientName: string;
  cardNumber: string;
  email: string;
  github: string;
  githubUrl: string;
  description: string;
}

export const donationConfig: DonationConfig = {
  recipientName: 'حسین محمدپور',
  cardNumber: '6219861931965403',
  email: 'info@hosseinmohammadpour.ir',
  github: 'satinbest/crmwp',
  githubUrl: 'https://github.com/satinbest/crmwp',
  description: 'اگر این پروژه برای شما مفید بوده، می‌توانید با حمایت خود به توسعه و نگهداری آن کمک کنید.',
};

export default donationConfig;
