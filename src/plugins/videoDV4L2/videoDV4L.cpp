////////////////////////////////////////////////////////
//
// GEM - Graphics Environment for Multimedia
//
// zmoelnig@iem.kug.ac.at
//
// Implementation file
//
//    Copyright (c) 1997-1998 Mark Danks.
//    Copyright (c) G�nther Geiger.
//    Copyright (c) 2001-2002 IOhannes m zmoelnig. forum::f�r::uml�ute. IEM
//    For information on usage and redistribution, and for a DISCLAIMER OF ALL
//    WARRANTIES, see the file, "GEM.LICENSE.TERMS" in this distribution.
//
/////////////////////////////////////////////////////////
#ifdef HAVE_CONFIG_H
# include "config.h"
#endif

#include "videoDV4L.h"
using namespace gem;

#include "Gem/RTE.h"

#ifdef HAVE_DV
#define N_BUF 2 /*DV1394_MAX_FRAMES/4*/

#if 0
# define DEBUG_WHERE post("%s:%d\t%s", __FILE__, __LINE__, __FUNCTION__)
#else
# define DEBUG_WHERE 
#endif


REGISTER_VIDEOFACTORY("dv4l2", videoDV4L);

/////////////////////////////////////////////////////////
//
// videoDV4L
//
/////////////////////////////////////////////////////////
// Constructor
//
/////////////////////////////////////////////////////////

videoDV4L :: videoDV4L() : video(),
                           m_raw(NULL),
                           m_decoder(NULL),
                           m_parsed(false)
{
  m_devicenum  = -1;
  m_quality=DV_QUALITY_BEST;

  int i=0;
  for(i=0; i<3; i++) {
    m_frame  [i] = NULL;
    m_pitches[i] = 0;
  }


  provide("dv4l2");
  provide("ieee1394");
  provide("dv4l");
  provide("dv");

  dv_init(1, 1); // singleton?
}

/////////////////////////////////////////////////////////
// Destructor
//
/////////////////////////////////////////////////////////
videoDV4L :: ~videoDV4L(){
  if(m_haveVideo)stopTransfer();
  if(m_decoder!=NULL)dv_decoder_free(m_decoder);

  dv_cleanup();
}


bool videoDV4L :: grabFrame(){
  /* this actually only transports the raw1394 stream
   * libiec will issue a callback when a frame is ready
   */
  fd_set rfds;
  if(m_dvfd<0)return false;
  struct timeval sleep;
  sleep.tv_sec=0;  sleep.tv_usec=10; /* 10us */

  FD_ZERO(&rfds);
  FD_SET(m_dvfd, &rfds);
  int rv = select(m_dvfd + 1, &rfds, NULL, NULL, &sleep);
  if(rv >= 0) {
    if(FD_ISSET(m_dvfd, &rfds)) {
      raw1394_loop_iterate(m_raw);
    }
  } else {
    perror("select");
  }
  return true;
}

int videoDV4L::decodeFrame(unsigned char*data, int len) {
  if(!m_parsed) {
    dv_parse_header(m_decoder, data);  
    if(NULL==m_frame[0]) {
      int w=m_decoder->width;
      int h=m_decoder->height;

      m_frame  [0]=new uint8_t[w*h*3];
      m_pitches[0]=w*3;

      lock();
      m_image.image.xsize=w;
      m_image.image.ysize=h;
      m_image.image.reallocate();
      unlock();
    }
    m_parsed=true;
  } else {
    dv_decode_full_frame(m_decoder, data,
                         e_dv_color_rgb,
                         m_frame,
                         m_pitches);
    
    lock();
    m_image.newimage=true;
    m_image.image.fromRGB(m_frame[0]);
    m_image.image.upsidedown=true;
    unlock();
  }

  return 0;
}

int videoDV4L::iec_frame(
                          unsigned char *data,
                          int len,
                          int complete,
                          void *arg
                          )
{
  //  post("iec_frame: %x/%d\t%d\t%x", data, len, complete, arg);
  if(complete) {
    videoDV4L*dv4l=(videoDV4L*)arg;
    return dv4l->decodeFrame(data, len);
  }
  return 0;
}

/////////////////////////////////////////////////////////
// openDevice
//
/////////////////////////////////////////////////////////
bool videoDV4L :: openDevice(){
  DEBUG_WHERE;
  if(m_raw)closeDevice();

  // LATER think about multithreading issues
  // according to the manual: "it is not allowed to use the same handle in multiple threads"
  // http://www.dennedy.org/libraw1394/API-raw1394-new-handle.html
  m_raw=raw1394_new_handle();

  int ports=0;

#if 0
  ports = raw1394_get_port_info(m_raw, NULL, 0);
#else
  int num_pinf=64;
  struct raw1394_portinfo*pinf=new struct raw1394_portinfo[num_pinf];
  
  ports = raw1394_get_port_info(m_raw, pinf, num_pinf);

  int i=0;
  for(i=0; i<ports; i++) {
    verbose(1, "port#%02d: %.*s", i, 32, pinf[i].name);
  }
  delete[]pinf;
#endif


  int devnum=m_devicenum;
  if(m_devicenum>=ports){
    closeDevice();
    return false;
  }
  if(devnum<0)devnum=0;
  if(raw1394_set_port(m_raw, devnum)<0) {
    perror("raw1394_set_port");
    closeDevice();
    return false;
  }


  if(NULL==m_raw)
    return false;


  m_dvfd = raw1394_get_fd(m_raw);
  if(m_dvfd<0) {
    closeDevice();
    return false;
  }
  return true;
}

/////////////////////////////////////////////////////////
// closeDevice
//
/////////////////////////////////////////////////////////
void videoDV4L :: closeDevice(void){
  DEBUG_WHERE;
  if(m_dvfd>=0)      ::close(m_dvfd);m_dvfd=-1;
  if(m_raw)          raw1394_destroy_handle(m_raw);m_raw=NULL;
}

/////////////////////////////////////////////////////////
// startTransfer
//
/////////////////////////////////////////////////////////
bool videoDV4L :: startTransfer()
{
  DEBUG_WHERE;
  m_image.newimage=0;
  m_image.image.data=0;
  m_image.image.xsize=720;
  m_image.image.ysize=576;
  m_image.image.setCsizeByFormat(m_reqFormat);
  m_image.image.reallocate();

  if(NULL==m_raw)return false;

  m_parsed=false;

  if(m_decoder!=NULL)dv_decoder_free(m_decoder);m_decoder=NULL;

  if (!(m_decoder=dv_decoder_new(true, true, true))){
    error("DV4L: unable to create DV-decoder...closing");
    return false;
  }

  m_decoder->quality=m_quality;
  verbose(1, "DV4L: DV decoding quality %d ", m_decoder->quality);

  m_iec = iec61883_dv_fb_init(m_raw, iec_frame, this);
  if(NULL==m_iec) {
    error("DV4L: unable to initialize IEC grabber");
    stopTransfer();
    return false;
  }

  if(iec61883_dv_fb_start(m_iec, 63) < 0) {
    error("DV4L: iec61883_dv_fb_start failed");
    stopTransfer();
    return false;
  }


  DEBUG_WHERE;
  return true;
}

/////////////////////////////////////////////////////////
// stopTransfer
//
/////////////////////////////////////////////////////////
bool videoDV4L :: stopTransfer()
{
  DEBUG_WHERE;
  /* close the dv4l device and dealloc buffer */
  /* terminate thread if there is one */
  //stopThread(100);

  if(m_iec) {
    iec61883_dv_fb_stop(m_iec);
  }

  if(m_decoder) {
    dv_decoder_free(m_decoder);

    m_decoder=NULL;
  }

  int i=0;
  for(i=0; i<3; i++) {
    if(m_frame[i]) delete[]m_frame[i]; m_frame[i] = NULL;
    m_pitches[i] = 0;
  }

  return(1);
}

int videoDV4L :: setDevice(int d){
  m_devicename=NULL;
  if (d==m_devicenum)return 0; // same device as before
  m_devicenum=d;

  bool running=false;

  running=stop();
  close();
  open();
  if(running)start();
  return 0;
}
int videoDV4L :: setDevice(char*name){
  // setting device by name not yet supported
  return 0;
}

int videoDV4L :: setColor(int format){
  if (format<=0)return -1;
  m_reqFormat=format;
  lock();
  m_image.image.setCsizeByFormat(m_reqFormat);
  unlock();
  return 0;
}

/////////////////////////////////////////
//
// Set the quality for DV decoding
//
/////////////////////////////////////////
int videoDV4L :: setQuality(int quality){
  if (quality<DV_QUALITY_FASTEST)return -1;
  if (quality>DV_QUALITY_BEST)return -1;
  m_quality=quality;

  if(m_decoder) {
    dv_set_quality(m_decoder, m_quality);
  }
  return 0;
}

#else // ! HAVE_DV
/////////////////////////////////////////////////////////
//
// videoDV4L without DV-support
//
videoDV4L :: videoDV4L() : video()
{ }
videoDV4L :: ~videoDV4L()
{ }
#endif // HAVE_DV
