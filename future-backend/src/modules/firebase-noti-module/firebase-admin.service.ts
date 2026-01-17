// src/firebase/firebase-admin.service.ts
import { Injectable } from '@nestjs/common';
import * as admin from 'firebase-admin';
import { Message } from 'firebase-admin/lib/messaging/messaging-api';

@Injectable()
export class FirebaseAdminService {
  private isInitialized = false;

  constructor() {
    if (!admin.apps.length) {
      const projectId = process.env.FIREBASE_PROJECT_ID;
      const clientEmail = process.env.FIREBASE_CLIENT_EMAIL;
      const privateKey = process.env.FIREBASE_PRIVATE_KEY?.replace(/\\n/g, '\n');

      // Only initialize if all credentials are provided and valid
      if (projectId && clientEmail && privateKey && privateKey.includes('-----BEGIN')) {
        try {
          console.log('Initializing Firebase Admin SDK...');
          const firebaseConfig = {
            projectId,
            clientEmail,
            privateKey,
          };

          admin.initializeApp({
            credential: admin.credential.cert(firebaseConfig),
          });
          this.isInitialized = true;
          console.log('Firebase Admin SDK initialized successfully');
        } catch (error) {
          console.warn('Firebase Admin SDK initialization failed, push notifications will be disabled:', error.message);
          this.isInitialized = false;
        }
      } else {
        console.warn('Firebase credentials not configured, push notifications will be disabled');
        this.isInitialized = false;
      }
    } else {
      this.isInitialized = true;
    }
  }

  async sendMessageToToken(token: string, title: string, body?: string, data?: Record<string, string>): Promise<string> {
    if (!this.isInitialized) {
      console.warn('[FirebaseAdminService] Push notification skipped - Firebase not initialized');
      return 'skipped';
    }

    const message: Message = {
      notification: {
        title,
        body,
      },
      token,
      data
    };

    try {
      const response = await admin.messaging().send(message);
      console.log('[FirebaseAdminService][sendMessageToToken]✅ Message sent:', response);
      return response;
    } catch (error) {
      console.error('[FirebaseAdminService][sendMessageToToken]-error: ❌ Failed to send message:', error);
      console.error(`[FirebaseAdminService][sendMessageToToken]-errorData: ${JSON.stringify(data)}`)
      throw error;
    }
  }
}
